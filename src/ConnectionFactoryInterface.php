<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool;

/**
 * @template TConnection of object
 */
interface ConnectionFactoryInterface
{
    /**
     * @return TConnection
     */
    public function create(): mixed;
}
