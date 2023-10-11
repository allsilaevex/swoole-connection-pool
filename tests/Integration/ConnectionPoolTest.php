<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Integration;

use Swoole\Coroutine;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\ConnectionPool\ConnectionPool;
use Allsilaevex\ConnectionPool\ConnectionFactories\PDOConnectionFactory;

use function Swoole\Coroutine\batch;

#[CoversClass(ConnectionPool::class)]
#[UsesClass(PDOConnectionFactory::class)]
class ConnectionPoolTest extends TestCase
{
    public function testBorrowAndReturnConnection(): void
    {
        $connectionPool = $this->createConnectionPool();

        /** @var \PDO $connection */
        $connection = $connectionPool->borrow();

        $statement = $connection->query('select 42');

        static::assertNotFalse($statement);
        static::assertEquals(42, $statement->fetchColumn());

        $connectionPool->return($connection);

        static::assertNull($connection);
    }

    public function testConcurrencyBorrowConnection(): void
    {
        $connectionPool = $this->createConnectionPool();

        /** @var \PDO $connection */
        $connection = $connectionPool->borrow();

        $connection->exec('CREATE TABLE test_table (id integer primary key, data text not null)');
        $connection->exec("insert into test_table (id, data) values (42, 'test')");

        $statement = $connection->query('select data from test_table where id = 42');

        static::assertNotFalse($statement);
        static::assertEquals('test', $statement->fetchColumn());

        $connectionPool->return($connection);

        $task1 = static function () use ($connectionPool): void {
            Coroutine::sleep(0.1);

            /** @var \PDO $connection */
            $connection = $connectionPool->borrow();

            $connection->exec("update test_table set data='second' where id = 42");

            $connectionPool->return($connection);
        };

        $task2 = static function () use ($connectionPool): void {
            /** @var \PDO $connection */
            $connection = $connectionPool->borrow();

            $connection->exec("update test_table set data='first' where id = 42");

            Coroutine::sleep(0.3);

            $connectionPool->return($connection);
        };

        batch([$task1, $task2]);

        /** @var \PDO $connection */
        $connection = $connectionPool->borrow();

        $statement = $connection->query('select data from test_table where id = 42');

        static::assertNotFalse($statement);
        static::assertEquals('second', $statement->fetchColumn());
    }

    public function testExceptionWhenCoroutineDidNotWaitForConnection(): void
    {
        $connectionPool = $this->createConnectionPool();

        $task1 = static function () use ($connectionPool): void {
            $connectionPool->borrow();
            Coroutine::sleep(.7);
        };

        $task2 = static function () use ($connectionPool): bool {
            Coroutine::sleep(.1);

            try {
                $connectionPool->borrow();
            } catch (\Throwable) {
                return false;
            }

            return true;
        };

        $results = batch([$task1, $task2]);

        static::assertFalse($results[1]);
    }

    /**
     * @return ConnectionPool<\PDO>
     */
    protected function createConnectionPool(): ConnectionPool
    {
        return new ConnectionPool(1, new PDOConnectionFactory('sqlite::memory:'));
    }
}
