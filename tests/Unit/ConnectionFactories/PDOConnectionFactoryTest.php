<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Test\Unit\ConnectionFactories;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\ConnectionPool\ConnectionFactories\PDOConnectionFactory;

#[CoversClass(PDOConnectionFactory::class)]
class PDOConnectionFactoryTest extends TestCase
{
    public function testExceptionOnEmptyPool(): void
    {
        $factory = new PDOConnectionFactory('sqlite::memory:');

        $connection = $factory->create();

        static::assertEquals('sqlite', $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
    }
}
