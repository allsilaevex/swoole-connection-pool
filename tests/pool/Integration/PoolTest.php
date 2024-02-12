<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Integration;

use stdClass;
use Throwable;
use Psr\Log\NullLogger;
use Allsilaevex\Pool\Pool;
use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolConfig;
use Allsilaevex\Pool\PoolMetrics;
use Allsilaevex\Pool\PoolItemWrapper;
use PHPUnit\Framework\Attributes\UsesClass;
use Allsilaevex\Pool\PoolItemWrapperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

use function mb_strlen;

#[CoversClass(Pool::class)]
#[UsesClass(PoolConfig::class)]
#[UsesClass(PoolMetrics::class)]
#[UsesClass(PoolItemWrapper::class)]
#[UsesClass(PoolItemWrapperFactory::class)]
class PoolTest extends TestCase
{
    public function testBorrowAndReturnItem(): void
    {
        $pool = $this->createSimplePool(size: 1);

        /** @var stdClass&object{id: non-empty-string} $item */
        $item = $pool->borrow();

        static::assertObjectHasProperty('id', $item);
        static::assertGreaterThan(0, mb_strlen($item->id));

        $pool->return($item);

        static::assertNull($item);
    }

    public function testThatDifferentItemsUsedInCoroutines(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<stdClass>
         */ class() implements PoolItemFactoryInterface {
            /**
             * @return stdClass
             */
            public function create(): mixed
            {
                $obj = new stdClass();
                $obj->id = 0;

                return $obj;
            }
        };

        $pool = $this->createSimplePool(size: 2, factory: $factory);

        /** @var list<object{id: int}> $usedItems */
        $usedItems = [];

        $taskFactory = static function (int $id) use ($pool, &$usedItems): callable {
            return static function () use ($id, $pool, &$usedItems): void {
                $item = $pool->borrow();

                \Swoole\Coroutine::sleep(seconds: .001);

                $item->id = $id;
                $usedItems[] = $item;
            };
        };

        \Swoole\Coroutine\batch([$taskFactory(1), $taskFactory(2)]);

        static::assertNotEquals($usedItems[0]->id, $usedItems[1]->id);
    }

    public function testThatSameItemsUsedInCoroutine(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<stdClass>
         */ class() implements PoolItemFactoryInterface {
            public int $itemId = 0;

            /**
             * @return stdClass
             */
            public function create(): mixed
            {
                $obj = new stdClass();
                $obj->id = ++$this->itemId;

                return $obj;
            }
        };

        $pool = $this->createSimplePool(size: 2, factory: $factory);

        $task = static function () use ($pool): bool {
            $item = $pool->borrow();
            $item->id = 42;

            $anotherItem = $pool->borrow();

            return $item->id == $anotherItem->id;
        };

        $result = \Swoole\Coroutine\batch([$task]);

        static::assertTrue($result[0]);
    }

    public function testItemWillReturnAfterCoroutineEnds(): void
    {
        $pool = $this->createSimplePool(size: 1);

        $task = static function () use ($pool): void {
            $pool->borrow();
        };

        \Swoole\Coroutine\batch([$task]);

        try {
            $item = $pool->borrow();
        } catch (Throwable) {
            $item = null;
        }

        static::assertNotNull($item);
    }

    public function testExceptionWhenCoroutineDidNotWaitForItem(): void
    {
        $pool = $this->createSimplePool(size: 1);

        $task1 = static function () use ($pool): void {
            $pool->borrow();
            \Swoole\Coroutine::sleep(.5);
        };

        $task2 = static function () use ($pool): bool {
            \Swoole\Coroutine::sleep(.1);

            try {
                $pool->borrow();
            } catch (Throwable) {
                return false;
            }

            return true;
        };

        $results = \Swoole\Coroutine\batch([$task1, $task2]);

        static::assertFalse($results[1]);
    }

    public function testRespectingSizeLimit(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<object>
         */ class() implements PoolItemFactoryInterface {
            public int $itemCreatedCount = 0;

            public function create(): mixed
            {
                $this->itemCreatedCount++;

                return new stdClass();
            }
        };

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $size = 2;
        $pool = new Pool(
            name: 'test',
            config: new PoolConfig($size, .1, .1),
            logger: new NullLogger(),
            timerTaskScheduler: $timerTaskSchedulerMock,
            poolItemHookManager: null,
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factory,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        \Swoole\Coroutine\parallel(2 * $size, static function () use ($pool) {
            $item = $pool->borrow();

            \Swoole\Coroutine::sleep(.002);

            $pool->return($item);
        });

        static::assertEquals(2, $factory->itemCreatedCount);
    }

    public function testRespectingSizeLimitAsync(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<object>
         */ class() implements PoolItemFactoryInterface {
            public int $itemCreatedCount = 0;

            public function create(): mixed
            {
                // отдаем управление, как это делает например pdo
                \Swoole\Coroutine::sleep(.001);

                $this->itemCreatedCount++;

                return new stdClass();
            }
        };

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(2, .1, .1),
            logger: new NullLogger(),
            timerTaskScheduler: $timerTaskSchedulerMock,
            poolItemHookManager: null,
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factory,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        \Swoole\Coroutine\parallel(4, static function () use ($pool) {
            $item = $pool->borrow();

            \Swoole\Coroutine::sleep(.002);

            $pool->return($item);
        });

        static::assertEquals(2, $factory->itemCreatedCount);
    }

    public function testCorrectlyItemDeletionWhenAutoReturn(): void
    {
        $factory = new /**
         * @implements PoolItemFactoryInterface<object>
         */ class() extends stdClass implements PoolItemFactoryInterface {
            public int $count = 0;

            public function create(): mixed
            {
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

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1, true, true),
            logger: new NullLogger(),
            timerTaskScheduler: $timerTaskSchedulerMock,
            poolItemHookManager: null,
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factory,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        /** @var int $cid */
        $cid = \Swoole\Coroutine\go(static function () use (&$pool) {
            $item = $pool->borrow();

            $pool->return($item);

            \Swoole\Coroutine::yield();
        });

        static::assertEquals(1, $factory->count);

        $pool->decreaseItems();

        static::assertEquals(0, $factory->count);

        \Swoole\Coroutine::resume($cid);
    }

    /**
     * @param  positive-int                             $size
     * @param  PoolItemFactoryInterface<stdClass>|null  $factory
     *
     * @return Pool<stdClass>
     */
    protected function createSimplePool(int $size, ?PoolItemFactoryInterface $factory = null): Pool
    {
        $defaultFactory = new /**
         * @implements PoolItemFactoryInterface<stdClass>
         */ class() implements PoolItemFactoryInterface {
            public int $itemCreatedCount = 0;

            /**
             * @return stdClass
             */
            public function create(): mixed
            {
                $this->itemCreatedCount++;

                $obj = new stdClass();
                $obj->id = uniqid(prefix: 'test', more_entropy: true);

                return $obj;
            }
        };

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        /**
         * @var Pool<stdClass> $pool
         * @psalm-suppress InvalidArgument
         */
        $pool = new Pool(
            name: 'test',
            config: new PoolConfig($size, .1, .1, true, true),
            logger: new NullLogger(),
            timerTaskScheduler: $timerTaskSchedulerMock,
            poolItemHookManager: null,
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factory ?? $defaultFactory,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        return $pool;
    }
}
