<?php

declare(strict_types=1);

namespace Allsilaevex\Benchmark;

use stdClass;
use Allsilaevex\Pool\Pool;
use Allsilaevex\Pool\PoolConfig;
use PhpBench\Attributes as Bench;
use Allsilaevex\Pool\PoolItemWrapperFactory;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskScheduler;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

class PoolBench
{
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Assert('mode(variant.time.avg) < 0.001 second +/- 10%')]
    public function benchBorrowFromEmptyPool(): void
    {
        /*
         * The test shows that pool doesn't wait for borrowingTimeoutSec to expire
         * when trying to get an item from empty pool.
         */

        \Swoole\Coroutine\run(function () {
            $pool = $this->createPool(size: 2, itemCreationTimeout: .0);

            $item1 = $pool->borrow();
            $item2 = $pool->borrow();

            $pool->return($item1);
            $pool->return($item2);
        });
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Assert('mode(variant.time.avg) < 0.015 second +/- 10%')]
    public function benchBorrowWithLongItemCreation(): void
    {
        /*
         * The test shows that the pool, in case of a long time to create an item and is quickly used,
         * will not wait for creation, but will take the first freed item.
         */

        \Swoole\Coroutine\run(function () {
            $pool = $this->createPool(size: 2, itemCreationTimeout: .01);

            \Swoole\Coroutine\parallel(4, static function () use ($pool) {
                $item = $pool->borrow();

                \Swoole\Coroutine::sleep(.001);

                $pool->return($item);
            });
        });
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Assert('mode(variant.time.avg) < 0.015 second +/- 10%')]
    public function benchBorrowWithLongItemUsage(): void
    {
        /*
         * The test shows that the pool, in case of quick creation of an item and long use,
         * will not wait for the item to be returned, but will create a new one.
         */

        \Swoole\Coroutine\run(function () {
            $pool = $this->createPool(size: 4, itemCreationTimeout: .001);

            \Swoole\Coroutine\parallel(4, static function () use ($pool) {
                $item = $pool->borrow();

                \Swoole\Coroutine::sleep(.01);

                $pool->return($item);
            });
        });
    }

    /**
     * @param  positive-int  $size
     *
     * @return Pool<stdClass>
     */
    protected function createPool(int $size, float $itemCreationTimeout): Pool
    {
        /** @var TimerTaskSchedulerInterface<PoolItemWrapperInterface<stdClass>> $poolItemTimerTaskScheduler */
        $poolItemTimerTaskScheduler = new TimerTaskScheduler([]);

        return new Pool(
            name: 'test',
            config: new PoolConfig(
                size: $size,
                borrowingTimeoutSec: .1,
                returningTimeoutSec: .1,
                autoReturn: false,
                bindToCoroutine: false,
            ),
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $this->createFactory($itemCreationTimeout),
                poolItemTimerTaskScheduler: $poolItemTimerTaskScheduler,
            ),
        );
    }

    /**
     * @return PoolItemFactoryInterface<stdClass>
     */
    protected function createFactory(float $timeout): PoolItemFactoryInterface
    {
        return new /**
         * @implements PoolItemFactoryInterface<stdClass>
         */ class($timeout) implements PoolItemFactoryInterface {
            public function __construct(
                protected float $timeout,
            ) {
            }

            public function create(): mixed
            {
                if ($this->timeout > .0) {
                    \Swoole\Coroutine::sleep($this->timeout);
                }

                return new stdClass();
            }
        };
    }
}
