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
     * @return TItem
     * @throws \Allsilaevex\Pool\Exceptions\PoolItemRemovedException
     */
    public function getItem(): mixed;

    public function recreateItem(): void;

    public function getState(): PoolItemState;

    public function setState(PoolItemState $state): void;

    public function compareAndSetState(PoolItemState $expect, PoolItemState $update): bool;

    public function waitForCompareAndSetState(PoolItemState $expect, PoolItemState $update, float $timeoutSec): bool;

    public function close(): void;

    /**
     * @return array{item_lifetime_sec: float, current_state_duration_sec: float}
     */
    public function stats(): array;
}
