<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Hook;

enum PoolItemHook: string
{
    case AFTER_RETURN = 'after_return';
    case BEFORE_BORROW = 'before_borrow';
}
