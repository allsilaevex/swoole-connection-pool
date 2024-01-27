<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolItemWrapper;
use PHPUnit\Framework\Attributes\UsesClass;
use Allsilaevex\Pool\PoolItemWrapperFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

#[CoversClass(PoolItemWrapperFactory::class)]
#[UsesClass(PoolItemWrapper::class)]
class PoolItemWrapperFactoryTest extends TestCase
{
    public function testCreatePoolItemWrapper(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);
        $factoryMock->method('create')->willReturn('item');

        $poolItemTimerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);

        $poolItemTimerTaskSchedulerMock->expects(self::once())->method('bindTo');
        $poolItemTimerTaskSchedulerMock->expects(self::once())->method('run');
        $poolItemTimerTaskSchedulerMock->expects(self::once())->method('start');

        $poolItemWrapperFactory = new PoolItemWrapperFactory(
            factory: $factoryMock,
            poolItemTimerTaskScheduler: $poolItemTimerTaskSchedulerMock,
        );

        $poolItemWrapper = $poolItemWrapperFactory->create();

        static::assertEquals('item', $poolItemWrapper->getItem());

        $poolItemWrapper->close();
    }
}
