<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\TimerTask;

use WeakReference;

/**
 * @template TRunner of object
 */
trait TimerTaskSchedulerAwareTrait
{
    /** @var WeakReference<TimerTaskSchedulerInterface<TRunner>>|null */
    protected ?WeakReference $timerTaskSchedulerRef = null;

    /**
     * @param  TimerTaskSchedulerInterface<TRunner>  $timerTaskScheduler
     */
    public function setTimerTaskScheduler(TimerTaskSchedulerInterface $timerTaskScheduler): void
    {
        $this->timerTaskSchedulerRef = WeakReference::create($timerTaskScheduler);
    }
}
