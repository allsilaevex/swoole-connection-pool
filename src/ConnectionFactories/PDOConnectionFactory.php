<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\ConnectionFactories;

use PDO;
use Allsilaevex\ConnectionPool\ConnectionFactoryInterface;

/**
 * @implements ConnectionFactoryInterface<PDO>
 */
readonly class PDOConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected string $dsn,
        protected ?string $username = null,
        protected ?string $password = null,
        protected ?array $options = null,
    ) {
    }

    public function create(): mixed
    {
        return new PDO(
            dsn: $this->dsn,
            username: $this->username,
            password: $this->password,
            options: $this->options,
        );
    }
}
