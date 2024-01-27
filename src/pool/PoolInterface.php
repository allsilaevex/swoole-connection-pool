<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

/**
 * @template TItem of object
 */
interface PoolInterface
{
    /**
     * @return TItem
     * @throws Exceptions\BorrowTimeoutException
     */
    public function borrow(): mixed;

    /**
     * @param  TItem|null  $poolItemRef
     */
    public function return(mixed &$poolItemRef): void;

    /**
     * @return array{
     *     all_item_count: int,
     *     idled_item_count: int,
     *     borrowed_item_count: int,
     *     consumer_pending_count: int,
     *     borrowed_total: int,
     *     item_created_total: int,
     *     item_deleted_total: int,
     *     borrowing_timeouts_total: int,
     *     item_in_use_total_sec: float,
     *     item_creation_total_sec: float,
     *     waiting_for_item_borrowing_total_sec: float,
     * }
     */
    public function stats(): array;
}
