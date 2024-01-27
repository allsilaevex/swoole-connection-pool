<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\TimerTask;

/**
 * @template TRunner of object
 */
interface TimerTaskSchedulerInterface
{
    /**
     * @param  TRunner  $runner
     */
    public function bindTo(object $runner): void;

    public function run(): void;

    public function start(): void;

    public function stopTask(int $timerId): bool;

    public function stop(): void;
}
