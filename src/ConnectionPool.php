<?php

declare(strict_types=1);

namespace Allsilaevex\ConnectionPool;

use Swoole\Coroutine\Channel;

/**
 * @template TConnection of object
 * @implements ConnectionPoolInterface<TConnection>
 */
class ConnectionPool implements ConnectionPoolInterface
{
    protected Channel $storage;

    /**
     * @param  positive-int                             $size
     * @param  ConnectionFactoryInterface<TConnection>  $factory
     */
    public function __construct(
        int $size,
        protected ConnectionFactoryInterface $factory,
    ) {
        $this->storage = new Channel($size);

        while ($size--) {
            $this->storage->push($this->factory->create());
        }
    }

    public function borrow(): mixed
    {
        /** @var TConnection|false $item */
        $item = $this->storage->pop(0.5);

        if ($item === false) {
            throw new \RuntimeException('item borrow failed');
        }

        return $item;
    }

    public function return(mixed &$itemRef): void
    {
        $item = $itemRef;
        $itemRef = null;

        if ($this->storage->isFull()) {
            return;
        }

        $this->storage->push($item, 0.5);
    }
}
