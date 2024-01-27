<?php

declare(strict_types=1);

namespace Allsilaevex\Pool;

class PoolMetrics
{
    public function __construct(
        public int $borrowedTotal = 0,
        public int $itemCreatedTotal = 0,
        public int $itemDeletedTotal = 0,
        public int $borrowingTimeoutsTotal = 0,
        public float $itemInUseTotalSec = .0,
        public float $itemCreationTotalSec = .0,
        public float $waitingForItemBorrowingTotalSec = .0,
    ) {
    }
}
