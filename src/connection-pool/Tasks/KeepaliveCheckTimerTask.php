<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Tasks;

use LogicException;
use Allsilaevex\Pool\PoolItemState;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskInterface;
use Allsilaevex\ConnectionPool\KeepaliveCheckerInterface;
use Allsilaevex\Pool\Exceptions\PoolItemRemovedException;

use function is_null;

/**
 * @template TItem of object
 * @implements TimerTaskInterface<PoolItemWrapperInterface<TItem>>
 */
readonly class KeepaliveCheckTimerTask implements TimerTaskInterface
{
    /**
     * @param  KeepaliveCheckerInterface<TItem>  $keepaliveChecker
     */
    public function __construct(
        protected KeepaliveCheckerInterface $keepaliveChecker,
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

        if (!$runner->compareAndSetState(PoolItemState::IDLE, PoolItemState::RESERVED)) {
            return;
        }

        try {
            $isAlive = $this->keepaliveChecker->check($runner->getItem());
        } catch (PoolItemRemovedException) {
            throw new LogicException();
        }

        if (!$isAlive) {
            $runner->recreateItem();
        }

        $runner->setState(PoolItemState::IDLE);
    }

    public function getIntervalSec(): float
    {
        return $this->keepaliveChecker->getIntervalSec();
    }
}
