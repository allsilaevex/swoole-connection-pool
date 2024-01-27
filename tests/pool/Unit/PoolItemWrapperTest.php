<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use stdClass;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolItemState;
use Allsilaevex\Pool\PoolItemWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

#[CoversClass(PoolItemWrapper::class)]
class PoolItemWrapperTest extends TestCase
{
    public function testCreatedWithIdleState(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        static::assertEquals(PoolItemState::IDLE, $poolItemWrapper->getState());

        $poolItemWrapper->close();
    }

    public function testGetItem(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->expects(self::once())->method('create')->willReturn(value: 'item');

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        static::assertEquals('item', $poolItemWrapper->getItem());

        $poolItemWrapper->close();
    }

    public function testIdIsUnique(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper1 = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);
        $poolItemWrapper2 = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        static::assertNotEquals($poolItemWrapper1->getId(), $poolItemWrapper2->getId());
    }

    public function testCompareAndSetState(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        static::assertFalse($poolItemWrapper->compareAndSetState(PoolItemState::IN_USE, PoolItemState::RESERVED));
        static::assertTrue($poolItemWrapper->compareAndSetState(PoolItemState::IDLE, PoolItemState::RESERVED));

        $poolItemWrapper->close();
    }

    public function testWaitForCompareAndSetState(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        static::assertFalse($poolItemWrapper->waitForCompareAndSetState(PoolItemState::IN_USE, PoolItemState::RESERVED, .001));

        $task1 = static function () use (&$poolItemWrapper): bool {
            return $poolItemWrapper->waitForCompareAndSetState(PoolItemState::IN_USE, PoolItemState::RESERVED, .01);
        };

        $task2 = static function () use (&$poolItemWrapper): bool {
            return $poolItemWrapper->waitForCompareAndSetState(PoolItemState::IDLE, PoolItemState::IN_USE, .01);
        };

        $results = \Swoole\Coroutine\batch([$task1, $task2]);

        static::assertTrue($results[0]);
        static::assertTrue($results[1]);

        $poolItemWrapper->setState(PoolItemState::IDLE);

        $results = \Swoole\Coroutine\batch([$task2, $task1]);

        static::assertTrue($results[0]);
        static::assertTrue($results[1]);

        $poolItemWrapper->close();
    }

    public function testItemDeletedBeforeRecreate(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<object>
         */ class() extends stdClass implements PoolItemFactoryInterface {
            public int $count = 0;

            public function create(): mixed
            {
                if ($this->count > 0) {
                    throw new RuntimeException('test failed');
                }

                return new class($this) {
                    public function __construct(
                        protected stdClass $factory,
                    ) {
                        $this->factory->count++;
                    }

                    public function __destruct()
                    {
                        $this->factory->count--;
                    }
                };
            }
        };

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemWrapper = new PoolItemWrapper($factory, $timerTaskSchedulerMock);

        $poolItemWrapper->recreateItem();

        $poolItemWrapper->close();

        static::assertEquals(0, $factory->count);
    }
}
