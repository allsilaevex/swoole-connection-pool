<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

/**
 * @template TItem of object
 * @implements PoolItemWrapperFactoryInterface<TItem>
 */
class PoolItemWrapperFactory implements PoolItemWrapperFactoryInterface
{
    /**
     * @param  PoolItemFactoryInterface<TItem>  $factory
     * @param  TimerTaskSchedulerInterface<PoolItemWrapperInterface<TItem>> $poolItemTimerTaskScheduler
     */
    public function __construct(
        protected PoolItemFactoryInterface $factory,
        protected TimerTaskSchedulerInterface $poolItemTimerTaskScheduler,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws Exceptions\PoolItemCreationException
     */
    public function create(): PoolItemWrapperInterface
    {
        /** @psalm-suppress InvalidArgument */
        return new PoolItemWrapper(
            factory: $this->factory,
            timerTaskScheduler: clone $this->poolItemTimerTaskScheduler,
        );
    }
}
