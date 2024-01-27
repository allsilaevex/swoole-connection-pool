<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Hook;

use Allsilaevex\Pool\PoolItemWrapperInterface;

/**
 * @template TItem of object
 * @implements PoolItemHookManagerInterface<TItem>
 */
readonly class PoolItemHookManager implements PoolItemHookManagerInterface
{
    /** @var array<value-of<PoolItemHook>, list<PoolItemHookInterface<TItem>>> */
    protected array $hooks;

    /**
     * @param  list<PoolItemHookInterface<TItem>>  $hooks
     */
    public function __construct(array $hooks)
    {
        $this->hooks = $this->groupHooks($hooks);
    }

    /**
     * @inheritDoc
     */
    public function run(PoolItemHook $poolHook, PoolItemWrapperInterface $poolItemWrapper): void
    {
        $hooks = $this->hooks[$poolHook->value] ?? [];

        foreach ($hooks as $hook) {
            $hook->invoke($poolItemWrapper);
        }
    }

    /**
     * @param  list<PoolItemHookInterface<TItem>>  $hooks
     *
     * @return array<value-of<PoolItemHook>, list<PoolItemHookInterface<TItem>>>
     */
    protected function groupHooks(array $hooks): array
    {
        $grouped = [];

        foreach ($hooks as $hook) {
            $grouped[$hook->getHook()->value][] = $hook;
        }

        return $grouped;
    }
}
