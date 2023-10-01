<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Unit;

use PHPUnit\Framework\TestCase;
use Allsilaevex\ConnectionPool\Connection;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\ConnectionPool\ConnectionPool;

#[CoversClass(ConnectionPool::class)]
#[UsesClass(Connection::class)]
class ConnectionPoolTest extends TestCase
{
    public function testConnectionIsConnected(): void
    {
        $connectionPool = new ConnectionPool();
        $connection = $connectionPool->borrow();

        static::assertTrue($connection->isConnected());
    }
}
