<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\ConnectionPool\ConnectionPool;
use Allsilaevex\ConnectionPool\ConnectionFactoryInterface;

#[CoversClass(ConnectionPool::class)]
#[UsesClass(ConnectionFactoryInterface::class)]
class ConnectionPoolTest extends TestCase
{
    public function testExceptionOnEmptyPool(): void
    {
        $this->expectException(\RuntimeException::class);

        $factoryMock = $this->createMock(ConnectionFactoryInterface::class);
        $connectionPool = new ConnectionPool(1, $factoryMock);

        $connectionPool->borrow();
        $connectionPool->borrow();
    }

    public function testPoolOverflowTolerate(): void
    {
        $factoryMock = $this->createMock(ConnectionFactoryInterface::class);

        $factoryMock
            ->method('create')
            ->willReturn(new \stdClass());

        $connectionPool = new ConnectionPool(1, $factoryMock);

        $unnecessaryConnection = $factoryMock->create();

        $connectionPool->return($unnecessaryConnection);

        static::assertNull($unnecessaryConnection);
    }
}
