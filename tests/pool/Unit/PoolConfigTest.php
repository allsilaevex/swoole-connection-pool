<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\PoolConfig;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PoolConfig::class)]
class PoolConfigTest extends TestCase
{
    public function testInabilityCreateIncorrectConfigState(): void
    {
        $this->expectException(LogicException::class);

        new PoolConfig(
            size: 1,
            borrowingTimeoutSec: .1,
            returningTimeoutSec: .1,
            autoReturn: true,
            bindToCoroutine: false,
        );
    }
}
