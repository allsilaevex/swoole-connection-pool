<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Tasks;

use Allsilaevex\Pool\PoolItemState;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskInterface;

use function is_null;

/**
 * @template TItem of object
 * @implements TimerTaskInterface<PoolItemWrapperInterface<TItem>>
 */
readonly class PoolItemUpdaterTimerTask implements TimerTaskInterface
{
    public function __construct(
        public float $intervalSec,
        public float $maxLifetimeSec,
        public float $maxItemReservingWaitingTimeSec = .0,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function run(int $timerId, mixed $runnerRef): void
    {
        /** @var PoolItemWrapperInterface<TItem>|null $runner */
        $runner = $runnerRef->get();

        if (is_null($runner)) {
            return;
        }

        if ($this->maxItemReservingWaitingTimeSec == .0) {
            $isReserved = $runner->compareAndSetState(
                expect: PoolItemState::IDLE,
                update: PoolItemState::RESERVED,
            );
        } else {
            $isReserved = $runner->waitForCompareAndSetState(
                expect: PoolItemState::IDLE,
                update: PoolItemState::RESERVED,
                timeoutSec: $this->maxItemReservingWaitingTimeSec,
            );
        }

        if (!$isReserved) {
            return;
        }

        if ($runner->stats()['item_lifetime_sec'] > $this->maxLifetimeSec) {
            $runner->recreateItem();
        }

        $runner->setState(PoolItemState::IDLE);
    }

    public function getIntervalSec(): float
    {
        return $this->intervalSec;
    }
}
