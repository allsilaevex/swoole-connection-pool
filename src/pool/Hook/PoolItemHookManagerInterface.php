<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Hook;

use Allsilaevex\Pool\PoolItemWrapperInterface;

/**
 * @template TItem of object
 */
interface PoolItemHookManagerInterface
{
    /**
     * @param  PoolItemWrapperInterface<TItem>  $poolItemWrapper
     */
    public function run(PoolItemHook $poolHook, PoolItemWrapperInterface $poolItemWrapper): void;
}
