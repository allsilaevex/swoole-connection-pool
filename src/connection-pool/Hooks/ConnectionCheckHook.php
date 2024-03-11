<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool\Hooks;

use Psr\Log\LoggerInterface;
use Allsilaevex\Pool\Hook\PoolItemHook;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\Hook\PoolItemHookInterface;
use Allsilaevex\Pool\Exceptions\PoolItemCreationException;

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
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invoke(PoolItemWrapperInterface $poolItemWrapper): void
    {
        $item = $poolItemWrapper->getItem();
        $checker = $this->checker;

        if (is_null($item) || $checker($item)) {
            return;
        }

        try {
            $poolItemWrapper->recreateItem();
        } catch (PoolItemCreationException $exception) {
            $this->logger->error('Can\'t recreate item: ' . $exception->getMessage(), ['item_id' => $poolItemWrapper->getId()]);
        }
    }

    public function getHook(): PoolItemHook
    {
        return PoolItemHook::BEFORE_BORROW;
    }
}
