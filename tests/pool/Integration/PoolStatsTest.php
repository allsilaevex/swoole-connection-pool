<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Integration;

use stdClass;
use Throwable;
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

use function uniqid;

#[CoversClass(Pool::class)]
#[CoversClass(PoolMetrics::class)]
#[UsesClass(PoolConfig::class)]
#[UsesClass(PoolItemWrapper::class)]
#[UsesClass(PoolItemWrapperFactory::class)]
class PoolStatsTest extends TestCase
{
    public function testCounters(): void
    {
        $pool = $this->createPool(4);

        $assert = static function (int $allItemCount, int $idledItemCount, int $borrowedItemCount, int $consumerPendingCount) use (&$pool): void {
            $stats = $pool->stats();

            static::assertEquals($allItemCount, $stats['all_item_count'], 'all_item_count');
            static::assertEquals($idledItemCount, $stats['idled_item_count'], 'idled_item_count');
            static::assertEquals($borrowedItemCount, $stats['borrowed_item_count'], 'borrowed_item_count');
            static::assertEquals($consumerPendingCount, $stats['consumer_pending_count'], 'consumer_pending_count');
        };

        $item = $pool->borrow();

        $assert(allItemCount: 1, idledItemCount: 0, borrowedItemCount: 1, consumerPendingCount: 0);

        $pool->return($item);

        $assert(allItemCount: 1, idledItemCount: 1, borrowedItemCount: 0, consumerPendingCount: 0);

        $count = 6;
        $wg = new \Swoole\Coroutine\WaitGroup($count);

        while ($count--) {
            \Swoole\Coroutine\go(static function () use (&$wg, &$pool) {
                $item = $pool->borrow();

                \Swoole\Coroutine::sleep(.005);

                $pool->return($item);

                $wg->done();
            });
        }

        $assert(allItemCount: 4, idledItemCount: 0, borrowedItemCount: 4, consumerPendingCount: 2);

        $wg->wait();
    }

    public function testTotals(): void
    {
        $pool = $this->createPool(4, .001);

        \Swoole\Coroutine\parallel(5, static function () use (&$pool) {
            try {
                $item = $pool->borrow();

                \Swoole\Coroutine::sleep(.005);

                $pool->return($item);
            } catch (Throwable) {
            }
        });

        $pool->decreaseItems();
        $pool->decreaseItems();

        $stats = $pool->stats();

        static::assertEquals(4, $stats['borrowed_total']);
        static::assertEquals(4, $stats['item_created_total']);
        static::assertEquals(2, $stats['item_deleted_total']);
        static::assertEquals(1, $stats['borrowing_timeouts_total']);

        static::assertGreaterThan(4 * .004, $stats['item_in_use_total_sec']);
        static::assertGreaterThan(.0, $stats['item_creation_total_sec']);
        static::assertGreaterThan(.0, $stats['waiting_for_item_borrowing_total_sec']);
    }

    /**
     * @param  positive-int  $size
     *
     * @return Pool<stdClass>
     */
    protected function createPool(int $size, float $borrowingTimeoutSec = .1): Pool
    {
        $itemGenerator = static function (): stdClass {
            $obj = new stdClass();
            $obj->id = uniqid(prefix: 'test', more_entropy: true);

            return $obj;
        };

        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->method('create')->willReturnCallback($itemGenerator);

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        /** @var Pool<stdClass> $pool */
        $pool = new Pool(
            name: 'pool_name',
            config: new PoolConfig($size, $borrowingTimeoutSec, .1),
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factoryMock,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        return $pool;
    }
}
