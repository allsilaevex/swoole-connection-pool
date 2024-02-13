<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Integration;

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
use Allsilaevex\Pool\TimerTask\TimerTaskScheduler;
use Allsilaevex\ConnectionPool\ConnectionPoolFactory;
use Allsilaevex\ConnectionPool\Tasks\ResizerTimerTask;
use Allsilaevex\ConnectionPool\Tasks\LeakDetectionTimerTask;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerAwareTrait;
use Allsilaevex\ConnectionPool\Tasks\PoolItemUpdaterTimerTask;

#[CoversClass(ConnectionPoolFactory::class)]
#[UsesClass(Pool::class)]
#[UsesClass(PoolConfig::class)]
#[UsesClass(PoolMetrics::class)]
#[UsesClass(PoolItemWrapper::class)]
#[UsesClass(ResizerTimerTask::class)]
#[UsesClass(TimerTaskScheduler::class)]
#[UsesClass(LeakDetectionTimerTask::class)]
#[UsesClass(PoolItemWrapperFactory::class)]
#[UsesClass(PoolItemUpdaterTimerTask::class)]
#[UsesClass(TimerTaskSchedulerAwareTrait::class)]
class ConnectionPoolFactoryTest extends TestCase
{
    public function testInstantiate(): void
    {
        $connection = new stdClass();
        $connection->id = 1;

        $poolItemFactoryInterfaceMock = $this->createMock(PoolItemFactoryInterface::class);
        $poolItemFactoryInterfaceMock->method('create')->willReturn($connection);

        $connectionPoolFactory = new ConnectionPoolFactory(size: 1, factory: $poolItemFactoryInterfaceMock);

        $pool = $connectionPoolFactory->instantiate();

        /** @var stdClass&object{id: int} $connectionFromPool */
        $connectionFromPool = $pool->borrow();

        static::assertEquals($connection->id, $connectionFromPool->id);
    }
}
