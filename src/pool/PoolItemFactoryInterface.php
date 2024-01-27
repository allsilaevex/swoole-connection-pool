<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

/**
 * @template TItem of object
 */
interface PoolItemFactoryInterface
{
    /**
     * @return TItem
     * @throws Exceptions\PoolItemCreationException
     */
    public function create(): mixed;
}
