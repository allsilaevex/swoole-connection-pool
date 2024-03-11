<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

use LogicException;
use Swoole\Coroutine\Channel;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

use function hrtime;
use function uniqid;

/**
 * @template TItem of object
 * @implements PoolItemWrapperInterface<TItem>
 */
class PoolItemWrapper implements PoolItemWrapperInterface
{
    protected const CHANNEL_TIMEOUT_SEC = .001;

    /**
     * @var non-empty-string
     */
    protected string $id;

    /** @var TItem|null */
    protected mixed $item;

    protected float $itemCreatedAt;

    protected PoolItemState $state;

    protected float $stateUpdatedAt;

    /** @var array<value-of<PoolItemState>, Channel> */
    protected array $stateStatuses;

    /**
     * @param  PoolItemFactoryInterface<TItem>                               $factory
     * @param  TimerTaskSchedulerInterface<PoolItemWrapperInterface<TItem>>  $timerTaskScheduler
     *
     * @throws Exceptions\PoolItemCreationException
     */
    public function __construct(
        protected PoolItemFactoryInterface $factory,
        protected TimerTaskSchedulerInterface $timerTaskScheduler,
    ) {
        $this->id = uniqid('pool_item_', more_entropy: true);
        $this->state = PoolItemState::IDLE;
        $this->stateStatuses = [];
        $this->stateUpdatedAt = hrtime(true);

        $this->recreateItem();

        foreach (PoolItemState::cases() as $case) {
            $this->stateStatuses[$case->value] = new Channel();
        }

        $this->stateStatuses[PoolItemState::IDLE->value]->push(true, self::CHANNEL_TIMEOUT_SEC);

        $this->timerTaskScheduler->bindTo($this);
        $this->timerTaskScheduler->run();

        $this->timerTaskScheduler->start();
    }

    public function __destruct()
    {
        if ($this->state == PoolItemState::REMOVED) {
            return;
        }

        $this->close();
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getItem(): mixed
    {
        $this->selfCheck();

        return $this->item;
    }

    /**
     * @inheritDoc
     */
    public function recreateItem(): void
    {
        $this->selfCheck();

        // destruct first
        $this->item = null;
        $this->itemCreatedAt = .0;

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->item = $this->factory->create();

        $this->itemCreatedAt = hrtime(true);
    }

    public function getState(): PoolItemState
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function setState(PoolItemState $state): void
    {
        if ($state == PoolItemState::REMOVED) {
            throw new LogicException('Can\'t directly set REMOVED state (use close() method)');
        }

        $this->selfCheck();

        $currentState = $this->state;
        $statusesSnapshot = $this->takeStatusesSnapshot();

        if ($this->stateStatuses[$currentState->value]->pop(self::CHANNEL_TIMEOUT_SEC) === false) {
            $debug = [
                'before pop' => [
                    'current state' => $currentState->value,
                    'new state' => $state->value,
                    'statuses' => $statusesSnapshot,
                ],
                'after pop' => [
                    'current state' => $this->state->value,
                    'new state' => $state->value,
                    'statuses' => $this->takeStatusesSnapshot(),
                ],
            ];

            throw new LogicException('debug info = ' . \json_encode($debug));
        }

        $this->state = $state;
        $this->stateUpdatedAt = hrtime(true);

        $statusesSnapshot = $this->takeStatusesSnapshot();

        if (!$this->stateStatuses[$state->value]->push(true, self::CHANNEL_TIMEOUT_SEC)) {
            $debug = [
                'before push' => [
                    'current state' => $state->value,
                    'statuses' => $statusesSnapshot,
                ],
                'after push' => [
                    'current state' => $this->state->value,
                    'statuses' => $this->takeStatusesSnapshot(),
                ],
            ];

            throw new LogicException('debug info = ' . \json_encode($debug));
        }
    }

    /**
     * @inheritDoc
     */
    public function compareAndSetState(PoolItemState $expect, PoolItemState $update): bool
    {
        if ($this->state == $expect) {
            $this->setState($update);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function waitForCompareAndSetState(PoolItemState $expect, PoolItemState $update, float $timeoutSec): bool
    {
        if ($update == PoolItemState::REMOVED) {
            throw new LogicException('Can\'t directly set REMOVED state (use close() method)');
        }

        $this->selfCheck();

        $result = $this->stateStatuses[$expect->value]->pop($timeoutSec);

        if ($result === false) {
            return false;
        }

        $this->state = $update;
        $this->stateUpdatedAt = hrtime(true);

        $statusesSnapshot = $this->takeStatusesSnapshot();

        if (!$this->stateStatuses[$update->value]->push(true, self::CHANNEL_TIMEOUT_SEC)) {
            $debug = [
                'before push' => [
                    'current state' => $update->value,
                    'statuses' => $statusesSnapshot,
                ],
                'after push' => [
                    'current state' => $this->state->value,
                    'statuses' => $this->takeStatusesSnapshot(),
                ],
            ];

            throw new LogicException('debug info = ' . \json_encode($debug));
        }

        return true;
    }

    public function close(): void
    {
        if ($this->state == PoolItemState::REMOVED) {
            return;
        }

        $this->state = PoolItemState::REMOVED;
        $this->stateUpdatedAt = hrtime(true);

        $this->timerTaskScheduler->stop();

        foreach ($this->stateStatuses as $stateStatus) {
            $stateStatus->close();
        }

        $this->item = null;
        $this->itemCreatedAt = hrtime(true);
    }

    /**
     * @inheritDoc
     */
    public function stats(): array
    {
        return [
            'item_lifetime_sec' => (hrtime(true) - $this->itemCreatedAt) * 1e-9,
            'current_state_duration_sec' => (hrtime(true) - $this->stateUpdatedAt) * 1e-9,
        ];
    }

    /**
     * @throws Exceptions\PoolItemRemovedException
     */
    protected function selfCheck(): void
    {
        if ($this->state == PoolItemState::REMOVED) {
            throw new Exceptions\PoolItemRemovedException();
        }
    }

    /**
     * @return array<value-of<PoolItemState>, array<mixed>>
     */
    protected function takeStatusesSnapshot(): array
    {
        return array_map(static fn (Channel $status) => $status->stats(), $this->stateStatuses);
    }
}
