<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool;

class ConnectionPool
{
    public function borrow(): Connection
    {
        return new Connection();
    }
}
