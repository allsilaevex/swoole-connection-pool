<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool;

/**
 * @template TConnection of object
 */
interface ConnectionPoolInterface
{
    /**
     * @return TConnection
     */
    public function borrow(): mixed;

    /**
     * @param  TConnection  $itemRef
     */
    public function return(mixed &$itemRef): void;
}
