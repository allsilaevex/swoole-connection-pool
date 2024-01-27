<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

/**
 * @template TItem of object
 */
interface PoolItemWrapperFactoryInterface
{
    /**
     * @return PoolItemWrapperInterface<TItem>
     */
    public function create(): PoolItemWrapperInterface;
}
