<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Unit;

use PHPUnit\Framework\TestCase;
use Allsilaevex\ConnectionPool\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Connection::class)]
class ConnectionTest extends TestCase
{
    public function testConnectionIsConnected(): void
    {
        $connection = new Connection();

        static::assertTrue($connection->isConnected());
    }
}
