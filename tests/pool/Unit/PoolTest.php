<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use stdClass;
use Allsilaevex\Pool\Pool;
use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolConfig;
use Allsilaevex\Pool\PoolMetrics;
use Allsilaevex\Pool\PoolItemWrapper;
use PHPUnit\Framework\Attributes\UsesClass;
use Allsilaevex\Pool\PoolItemWrapperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\PoolItemWrapperFactoryInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

#[CoversClass(Pool::class)]
#[UsesClass(PoolConfig::class)]
#[UsesClass(PoolMetrics::class)]
#[UsesClass(PoolItemWrapper::class)]
#[UsesClass(PoolItemWrapperFactory::class)]
class PoolTest extends TestCase
{
    public function testGetName(): void
    {
        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);

        $pool = new Pool(
            name: 'pool_name',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        static::assertEquals('pool_name', $pool->getName());
    }

    public function testGetConfig(): void
    {
        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);

        $pool = new Pool(
            name: 'pool_name',
            config: new PoolConfig(1, .2, .3),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        static::assertEquals(1, $pool->getConfig()->size);
        static::assertEquals(.2, $pool->getConfig()->borrowingTimeoutSec);
        static::assertEquals(.3, $pool->getConfig()->returningTimeoutSec);
    }

    public function testGetIdleCount(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->method('create')->willReturn(new stdClass());

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $pool = new Pool(
            name: 'pool_name',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factoryMock,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        $item = $pool->borrow();

        static::assertEquals(0, $pool->getIdleCount());

        $pool->return($item);

        static::assertEquals(1, $pool->getIdleCount());
    }

    public function testPoolOverflowTolerate(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->method('create')->willReturn(new stdClass());

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factoryMock,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        $unnecessaryItem = $factoryMock->create();

        $pool->return($unnecessaryItem);

        static::assertNull($unnecessaryItem);
        static::assertEquals(0, $pool->getCurrentSize());
    }

    public function testDecreaseOnEmptyPool(): void
    {
        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        static::assertEquals(0, $pool->getCurrentSize());
        static::assertFalse($pool->decreaseItems());
        static::assertEquals(0, $pool->getCurrentSize());
    }

    public function testIncreaseOnFilledPool(): void
    {
        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        $pool->increaseItems();

        static::assertEquals(1, $pool->getCurrentSize());
        static::assertFalse($pool->increaseItems());
        static::assertEquals(1, $pool->getCurrentSize());
    }

    public function testIncreaseWithCreationFail(): void
    {
        $exception = new \LogicException('testIncreaseWithCreationFail');

        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);
        $poolItemWrapperFactoryMock->expects(self::once())->method('create')->willThrowException($exception);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        try {
            $pool->increaseItems();

            static::fail();
        } catch (\Throwable $throwable) {
            static::assertEquals($exception->getMessage(), $throwable->getMessage());
        }

        static::assertEquals(0, $pool->getCurrentSize());
    }

    public function testIdledItemStorageGetter(): void
    {
        $poolItemWrapperFactoryMock = $this->createMock(PoolItemWrapperFactoryInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: $poolItemWrapperFactoryMock,
        );

        $idledItemStorage = $pool->getIdledItemStorage();

        static::assertEquals(0, $idledItemStorage->count());

        $pool->increaseItems();

        static::assertEquals(1, $idledItemStorage->count());
    }

    public function testBorrowedItemStorageGetter(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->method('create')->willReturn(new stdClass());

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $pool = new Pool(
            name: 'test',
            config: new PoolConfig(1, .1, .1),
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $factoryMock,
                poolItemTimerTaskScheduler: $timerTaskSchedulerMock,
            ),
        );

        $borrowedItemStorage = $pool->getBorrowedItemStorage();

        static::assertEquals(0, $borrowedItemStorage->count());

        $item = $pool->borrow();

        static::assertEquals(1, $borrowedItemStorage->count());

        $pool->return($item);
    }
}
