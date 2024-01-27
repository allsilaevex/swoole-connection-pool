<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Integration;

use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolItemState;
use Allsilaevex\Pool\PoolItemWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\PoolItemFactoryInterface;
use Allsilaevex\Pool\Exceptions\PoolItemRemovedException;
use Allsilaevex\Pool\TimerTask\TimerTaskSchedulerInterface;

use function hrtime;

#[CoversClass(PoolItemWrapper::class)]
class PoolItemWrapperTest extends TestCase
{
    public function testUnusabilityAfterClose(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);
        $timerTaskSchedulerMock->expects(self::once())->method('stop');

        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);
        $poolItemWrapper->close();

        static::assertEquals(PoolItemState::REMOVED, $poolItemWrapper->getState());

        $this->expectException(PoolItemRemovedException::class);

        $poolItemWrapper->getItem();
    }

    public function testStats(): void
    {
        $factoryMock = $this->createMock(PoolItemFactoryInterface::class);

        $timerTaskSchedulerMock = $this->createMock(TimerTaskSchedulerInterface::class);
        $timerTaskSchedulerMock->expects(self::once())->method('stop');

        $start = hrtime(true);
        $poolItemWrapper = new PoolItemWrapper($factoryMock, $timerTaskSchedulerMock);

        \Swoole\Coroutine::sleep(.005);

        $stats = $poolItemWrapper->stats();
        $elapsedSec = 1e-9 * (hrtime(true) - $start);

        static::assertLessThan($elapsedSec, $stats['item_lifetime_sec']);
        static::assertGreaterThan(.005, $stats['item_lifetime_sec']);

        static::assertLessThan($elapsedSec, $stats['current_state_duration_sec']);
        static::assertGreaterThan(.005, $stats['current_state_duration_sec']);

        $poolItemWrapper->close();
    }
}
