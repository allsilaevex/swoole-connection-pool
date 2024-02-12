<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

use LogicException;
use Swoole\Coroutine\Channel;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

use function hrtime;
use function uniqid;
use function is_null;

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
        $this->recreateItem();

        $this->id = uniqid('pool_item_', more_entropy: true);
        $this->state = PoolItemState::IDLE;
        $this->stateStatuses = [];
        $this->stateUpdatedAt = hrtime(true);

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
        if ($this->state == PoolItemState::REMOVED || is_null($this->item)) {
            throw new Exceptions\PoolItemRemovedException('Getting item not allowed (item already removed)');
        }

        return $this->item;
    }

    /**
     * @throws Exceptions\PoolItemCreationException
     */
    public function recreateItem(): void
    {
        // destruct first
        $this->item = null;

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->item = $this->factory->create();

        $this->itemCreatedAt = hrtime(true);
    }

    public function getState(): PoolItemState
    {
        return $this->state;
    }

    public function setState(PoolItemState $state): void
    {
        if ($this->stateStatuses[$this->state->value]->pop(self::CHANNEL_TIMEOUT_SEC) === false) {
            throw new LogicException();
        }

        $this->state = $state;
        $this->stateUpdatedAt = hrtime(true);

        if (!$this->stateStatuses[$state->value]->push(true, self::CHANNEL_TIMEOUT_SEC)) {
            throw new LogicException();
        }
    }

    public function compareAndSetState(PoolItemState $expect, PoolItemState $update): bool
    {
        if ($this->state == $expect) {
            $this->setState($update);
            return true;
        }

        return false;
    }

    public function waitForCompareAndSetState(PoolItemState $expect, PoolItemState $update, float $timeoutSec): bool
    {
        $result = $this->stateStatuses[$expect->value]->pop($timeoutSec);

        if ($result === false) {
            return false;
        }

        $this->state = $update;
        $this->stateUpdatedAt = hrtime(true);

        if (!$this->stateStatuses[$update->value]->push(true, self::CHANNEL_TIMEOUT_SEC)) {
            throw new LogicException();
        }

        return true;
    }

    public function close(): void
    {
        $this->setState(PoolItemState::REMOVED);

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
}
