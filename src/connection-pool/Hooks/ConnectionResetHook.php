<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Hooks;

use Allsilaevex\Pool\Hook\PoolItemHook;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\Hook\PoolItemHookInterface;

/**
 * @template TItem of object
 * @implements PoolItemHookInterface<TItem>
 */
readonly class ConnectionResetHook implements PoolItemHookInterface
{
    /**
     * @param  callable(TItem): void  $resetter
     */
    public function __construct(
        protected mixed $resetter,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invoke(PoolItemWrapperInterface $poolItemWrapper): void
    {
        $resetter = $this->resetter;

        $resetter($poolItemWrapper->getItem());
    }

    public function getHook(): PoolItemHook
    {
        return PoolItemHook::AFTER_RETURN;
    }
}
