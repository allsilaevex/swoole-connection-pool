<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

use Throwable;
use WeakReference;
use LogicException;
use SplObjectStorage;
use Swoole\Coroutine;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use Allsilaevex\Pool\Hook\PoolItemHook;
use Allsilaevex\Pool\Hook\PoolItemHookManagerInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

use function max;
use function hrtime;
use function is_null;
use function sprintf;
use function array_key_exists;

/**
 * @template TItem of object
 *
 * @implements PoolInterface<TItem>
 * @implements PoolControlInterface<TItem>
 */
class Pool implements PoolInterface, PoolControlInterface
{
    protected PoolMetrics $metrics;

    /** @var SplObjectStorage<TItem, PoolItemWrapperInterface<TItem>> */
    protected SplObjectStorage $borrowedItemStorage;

    /** @var SplObjectStorage<PoolItemWrapperInterface<TItem>, float> */
    protected SplObjectStorage $idledItemStorage;

    protected Channel $concurrentBag;

    /** @var array<int, TItem> */
    protected array $itemToCoroutineBindings;

    protected int $itemWrapperCount;

    /**
     * @param  non-empty-string                                               $name
     * @param  PoolItemWrapperFactoryInterface<TItem>                         $poolItemWrapperFactory
     * @param  TimerTaskSchedulerInterface<PoolControlInterface<TItem>>|null  $timerTaskScheduler
     * @param  PoolItemHookManagerInterface<TItem>|null                       $poolItemHookManager
     */
    public function __construct(
        protected string $name,
        protected PoolConfig $config,
        protected PoolItemWrapperFactoryInterface $poolItemWrapperFactory,
        protected LoggerInterface $logger = new NullLogger(),
        protected ?TimerTaskSchedulerInterface $timerTaskScheduler = null,
        protected ?PoolItemHookManagerInterface $poolItemHookManager = null,
    ) {
        $this->metrics = new PoolMetrics();
        $this->concurrentBag = new Channel($config->size);
        $this->itemWrapperCount = 0;
        $this->idledItemStorage = new SplObjectStorage();
        $this->borrowedItemStorage = new SplObjectStorage();
        $this->itemToCoroutineBindings = [];

        $this->timerTaskScheduler?->bindTo($this);
        $this->timerTaskScheduler?->run();

        $this->timerTaskScheduler?->start();
    }

    public function __destruct()
    {
        $this->timerTaskScheduler?->stop();

        // @phpstan-ignore-next-line
        $this->idledItemStorage->removeAll($this->idledItemStorage);

        // @phpstan-ignore-next-line
        $this->borrowedItemStorage->removeAll($this->borrowedItemStorage);

        $this->concurrentBag->close();
    }

    /**
     * @inheritDoc
     */
    public function borrow(): mixed
    {
        $cid = Coroutine::getCid();

        if ($this->config->bindToCoroutine && array_key_exists($cid, $this->itemToCoroutineBindings)) {
            return $this->itemToCoroutineBindings[$cid];
        }

        $start = hrtime(true);
        $poolItemWrapper = $this->getPoolItemWrapper();
        $timeLeft = max(.0001, $this->config->borrowingTimeoutSec - (hrtime(true) - $start) * 1e-9);

        if (is_null($this->poolItemHookManager)) {
            $stateForUpdate = PoolItemState::IN_USE;
        } else {
            $stateForUpdate = PoolItemState::RESERVED;
        }

        if (!$poolItemWrapper->waitForCompareAndSetState(PoolItemState::IDLE, $stateForUpdate, $timeLeft)) {
            $context = [
                'pool_name' => $this->getName(),
                'item_id' => $poolItemWrapper->getId(),
                'item_old_state' => $poolItemWrapper->getState()->name,
                'item_new_state' => $stateForUpdate->name,
            ];
            $errorMessage = sprintf(
                'Can\'t set %s state (old state %s)',
                $stateForUpdate->name,
                $poolItemWrapper->getState()->name,
            );

            $this->logger->warning($errorMessage, $context);

            $this->metrics->borrowingTimeoutsTotal++;

            throw new Exceptions\BorrowTimeoutException($errorMessage);
        }

        if (!is_null($this->poolItemHookManager)) {
            $this->poolItemHookManager->run(PoolItemHook::BEFORE_BORROW, $poolItemWrapper);

            if (!$poolItemWrapper->compareAndSetState(PoolItemState::RESERVED, PoolItemState::IN_USE)) {
                throw new LogicException();
            }
        }

        $item = $poolItemWrapper->getItem();

        $this->idledItemStorage->detach($poolItemWrapper);
        $this->borrowedItemStorage->attach($item, $poolItemWrapper);

        if ($this->config->bindToCoroutine) {
            $this->itemToCoroutineBindings[$cid] = $item;
        }

        if ($this->config->autoReturn) {
            $itemRef = WeakReference::create($item);

            Coroutine::defer(function () use ($cid, $itemRef) {
                unset($this->itemToCoroutineBindings[$cid]);

                $item = $itemRef->get();

                $this->return($item);
            });
        }

        $this->metrics->borrowedTotal++;
        $this->metrics->waitingForItemBorrowingTotalSec += 1e-9 * (hrtime(true) - $start);

        return $item;
    }

    /**
     * @inheritDoc
     */
    public function return(mixed &$poolItemRef): void
    {
        if ($poolItemRef === null) {
            return;
        }

        $poolItem = $poolItemRef;
        $poolItemRef = null;

        if (!$this->borrowedItemStorage->contains($poolItem)) {
            return;
        }

        /** @var PoolItemWrapperInterface<TItem> $poolItemWrapper */
        $poolItemWrapper = $this->borrowedItemStorage[$poolItem];

        $this->borrowedItemStorage->detach($poolItem);

        unset($this->itemToCoroutineBindings[Coroutine::getCid()]);

        if ($this->concurrentBag->isFull()) {
            return;
        }

        if ($poolItemWrapper->getState() != PoolItemState::IN_USE) {
            throw new LogicException();
        }

        $this->metrics->itemInUseTotalSec += $poolItemWrapper->stats()['current_state_duration_sec'];

        if (is_null($this->poolItemHookManager)) {
            $poolItemWrapper->setState(PoolItemState::IDLE);
        } else {
            $poolItemWrapper->setState(PoolItemState::RESERVED);

            $this->poolItemHookManager->run(PoolItemHook::AFTER_RETURN, $poolItemWrapper);

            if (!$poolItemWrapper->compareAndSetState(PoolItemState::RESERVED, PoolItemState::IDLE)) {
                throw new LogicException();
            }
        }

        $this->idledItemStorage->attach($poolItemWrapper, hrtime(true));

        $isReturned = $this->concurrentBag->push($poolItemWrapper, $this->config->returningTimeoutSec);

        if (!$isReturned) {
            $this->idledItemStorage->detach($poolItemWrapper);
        }
    }

    /**
     * @inheritDoc
     */
    public function stats(): array
    {
        return [
            'all_item_count' => $this->getCurrentSize(),
            'idled_item_count' => $this->getIdleCount(),
            'borrowed_item_count' => $this->borrowedItemStorage->count(),
            /** @phpstan-ignore-next-line */
            'consumer_pending_count' => (int)$this->concurrentBag->stats()['consumer_num'],

            'borrowed_total' => $this->metrics->borrowedTotal,
            'item_created_total' => $this->metrics->itemCreatedTotal,
            'item_deleted_total' => $this->metrics->itemDeletedTotal,
            'borrowing_timeouts_total' => $this->metrics->borrowingTimeoutsTotal,

            'item_in_use_total_sec' => $this->metrics->itemInUseTotalSec,
            'item_creation_total_sec' => $this->metrics->itemCreationTotalSec,
            'waiting_for_item_borrowing_total_sec' => $this->metrics->waitingForItemBorrowingTotalSec,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getIdleCount(): int
    {
        return $this->concurrentBag->length();
    }

    public function getCurrentSize(): int
    {
        return $this->itemWrapperCount;
    }

    /**
     * @inheritDoc
     */
    public function getIdledItemStorage(): SplObjectStorage
    {
        return $this->idledItemStorage;
    }

    /**
     * @inheritDoc
     */
    public function getBorrowedItemStorage(): SplObjectStorage
    {
        return $this->borrowedItemStorage;
    }

    public function getConfig(): PoolConfig
    {
        return $this->config;
    }

    public function increaseItems(): bool
    {
        if ($this->concurrentBag->isFull()) {
            return false;
        }

        $this->itemWrapperCount++;

        $start = hrtime(true);

        try {
            $poolItemWrapper = $this->poolItemWrapperFactory->create();
        } catch (Throwable $throwable) {
            $this->itemWrapperCount--;
            throw $throwable;
        }

        $this->metrics->itemCreatedTotal++;
        $this->metrics->itemCreationTotalSec += 1e-9 * (hrtime(true) - $start);

        $this->idledItemStorage->attach($poolItemWrapper, hrtime(true));

        $result = $this->concurrentBag->push($poolItemWrapper, .001);

        if ($result === false) {
            $this->idledItemStorage->detach($poolItemWrapper);

            $poolItemWrapper->close();
            $poolItemWrapper = null;

            $this->itemWrapperCount--;
            $this->metrics->itemDeletedTotal++;
        }

        return $result;
    }

    public function decreaseItems(): bool
    {
        if ($this->concurrentBag->isEmpty()) {
            return false;
        }

        /** @var PoolItemWrapperInterface<TItem>|false $poolItemWrapper */
        $poolItemWrapper = $this->concurrentBag->pop(.001);

        if ($poolItemWrapper === false) {
            return false;
        }

        $this->idledItemStorage->detach($poolItemWrapper);

        $poolItemWrapper->close();
        $poolItemWrapper = null;

        $this->itemWrapperCount--;
        $this->metrics->itemDeletedTotal++;

        return true;
    }

    /**
     * @return PoolItemWrapperInterface<TItem>
     * @throws Exceptions\BorrowTimeoutException
     */
    protected function getPoolItemWrapper(): PoolItemWrapperInterface
    {
        if ($this->concurrentBag->isEmpty() && $this->getCurrentSize() < $this->config->size) {
            \Swoole\Coroutine\go(function () {
                $this->increaseItems();
            });
        }

        /** @var PoolItemWrapperInterface<TItem>|false $poolItemWrapper */
        $poolItemWrapper = $this->concurrentBag->pop($this->config->borrowingTimeoutSec);

        if ($poolItemWrapper === false) {
            $this->metrics->borrowingTimeoutsTotal++;

            throw new Exceptions\BorrowTimeoutException('Can\'t pop item from concurrentBag');
        }

        return $poolItemWrapper;
    }
}
