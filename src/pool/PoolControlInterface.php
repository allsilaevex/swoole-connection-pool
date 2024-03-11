<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

use SplObjectStorage;

/**
 * @template TItem of object
 */
interface PoolControlInterface
{
    /**
     * @return non-empty-string
     */
    public function getName(): string;

    public function getConfig(): PoolConfig;

    public function getIdleCount(): int;

    public function getCurrentSize(): int;

    /**
     * @throws \Throwable
     */
    public function increaseItems(): bool;

    public function decreaseItems(): bool;

    /**
     * @return SplObjectStorage<PoolItemWrapperInterface<TItem>, float>
     */
    public function getIdledItemStorage(): SplObjectStorage;

    /**
     * @return SplObjectStorage<TItem, PoolItemWrapperInterface<TItem>>
     */
    public function getBorrowedItemStorage(): SplObjectStorage;
}
