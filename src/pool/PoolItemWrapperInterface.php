<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

/**
 * @template TItem of object
 */
interface PoolItemWrapperInterface
{
    /**
     * @return non-empty-string
     */
    public function getId(): string;

    /**
     * @return TItem|null
     * @throws Exceptions\PoolItemRemovedException
     */
    public function getItem(): mixed;

    /**
     * @throws Exceptions\PoolItemCreationException
     * @throws Exceptions\PoolItemRemovedException
     */
    public function recreateItem(): void;

    public function getState(): PoolItemState;

    /**
     * @throws Exceptions\PoolItemRemovedException
     */
    public function setState(PoolItemState $state): void;

    /**
     * @throws Exceptions\PoolItemRemovedException
     */
    public function compareAndSetState(PoolItemState $expect, PoolItemState $update): bool;

    /**
     * @throws Exceptions\PoolItemRemovedException
     */
    public function waitForCompareAndSetState(PoolItemState $expect, PoolItemState $update, float $timeoutSec): bool;

    public function close(): void;

    /**
     * @return array{item_lifetime_sec: float, current_state_duration_sec: float}
     */
    public function stats(): array;
}
