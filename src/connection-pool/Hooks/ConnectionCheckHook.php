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
readonly class ConnectionCheckHook implements PoolItemHookInterface
{
    /**
     * @param  callable(TItem): bool  $checker
     */
    public function __construct(
        protected mixed $checker,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invoke(PoolItemWrapperInterface $poolItemWrapper): void
    {
        $item = $poolItemWrapper->getItem();
        $checker = $this->checker;

        if ($checker($item)) {
            return;
        }

        $poolItemWrapper->recreateItem();
    }

    public function getHook(): PoolItemHook
    {
        return PoolItemHook::BEFORE_BORROW;
    }
}
