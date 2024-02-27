<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use stdClass;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\TimerTask\TimerTaskInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskScheduler;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerAwareTrait;

use function is_null;

#[CoversClass(TimerTaskScheduler::class)]
#[CoversClass(TimerTaskSchedulerAwareTrait::class)]
class TimerTaskSchedulerTest extends TestCase
{
    public function testRunWithoutBinding(): void
    {
        $this->expectException(LogicException::class);

        $timerTaskScheduler = new TimerTaskScheduler([]);
        $timerTaskScheduler->run();
    }

    public function testStartWithoutBinding(): void
    {
        $this->expectException(LogicException::class);

        $timerTaskScheduler = new TimerTaskScheduler([]);
        $timerTaskScheduler->start();
    }

    public function testRun(): void
    {
        $timerTaskScheduler = new TimerTaskScheduler([
            $this->createTimerTaskInterface(),
        ]);

        /** @var stdClass&object{counter: int} $runner */
        $runner = new stdClass();
        $runner->counter = 0;

        $timerTaskScheduler->bindTo($runner);
        $timerTaskScheduler->run();

        static::assertEquals(1, $runner->counter);

        \Swoole\Coroutine::sleep(.003);

        static::assertEquals(1, $runner->counter);
    }

    public function testStart(): void
    {
        $timerTaskScheduler = new TimerTaskScheduler([
            $this->createTimerTaskInterface(),
        ]);

        /** @var stdClass&object{counter: int} $runner */
        $runner = new stdClass();
        $runner->counter = 0;

        $timerTaskScheduler->bindTo($runner);
        $timerTaskScheduler->start();

        static::assertEquals(0, $runner->counter);

        \Swoole\Coroutine::sleep(.003);

        static::assertGreaterThanOrEqual(1, $runner->counter);

        $timerTaskScheduler->stop();
    }

    public function testTaskStopping(): void
    {
        $timerTaskScheduler = new TimerTaskScheduler([
            $this->createTimerTaskInterface(),
        ]);

        /** @var stdClass&object{counter: int} $runner */
        $runner = new stdClass();
        $runner->counter = 0;

        $timerTaskScheduler->bindTo($runner);
        $timerTaskScheduler->start();

        static::assertEquals(0, $runner->counter);

        \Swoole\Coroutine::sleep(.02);

        static::assertEquals(2, $runner->counter);

        $timerTaskScheduler->stop();
    }

    public function testRebindingAfterStart(): void
    {
        $timerTaskScheduler = new TimerTaskScheduler([
            $this->createTimerTaskInterface(),
        ]);

        /** @var stdClass&object{counter: int} $runner1 */
        $runner1 = new stdClass();
        $runner1->id = 1;
        $runner1->counter = 0;

        /** @var stdClass&object{counter: int} $runner2 */
        $runner2 = new stdClass();
        $runner2->id = 2;
        $runner2->counter = 0;

        $timerTaskScheduler->bindTo($runner1);
        $timerTaskScheduler->start();

        static::assertEquals(0, $runner1->counter);
        static::assertEquals(0, $runner2->counter);

        \Swoole\Coroutine::sleep(.003);

        static::assertGreaterThanOrEqual(1, $runner1->counter);
        static::assertEquals(0, $runner2->counter);

        $timerTaskScheduler->bindTo($runner2);

        \Swoole\Coroutine::sleep(.003);

        static::assertGreaterThanOrEqual(1, $runner1->counter);
        static::assertGreaterThanOrEqual(1, $runner2->counter);

        $timerTaskScheduler->stop();
    }

    /**
     * @return TimerTaskInterface<stdClass&object{counter: int}>
     */
    protected function createTimerTaskInterface(): TimerTaskInterface
    {
        return new /**
         * @implements TimerTaskInterface<stdClass&object{counter: int}>
         */ class() implements TimerTaskInterface {
            /** @phpstan-use TimerTaskSchedulerAwareTrait<stdClass&object{counter: int}> */
            use TimerTaskSchedulerAwareTrait;

            public function run(int $timerId, mixed $runnerRef): void
            {
                $runner = $runnerRef->get();

                if (is_null($runner)) {
                    return;
                }

                $runner->counter++;

                if ($runner->counter == 2) {
                    $this->timerTaskSchedulerRef?->get()?->stopTask($timerId);
                }
            }

            public function getIntervalSec(): float
            {
                return .002;
            }
        };
    }
}
