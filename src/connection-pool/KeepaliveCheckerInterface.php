<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool;

/**
 * @template TConnection of object
 */
interface KeepaliveCheckerInterface
{
    /**
     * @param  TConnection  $connection
     */
    public function check(mixed $connection): bool;

    public function getIntervalSec(): float;
}
