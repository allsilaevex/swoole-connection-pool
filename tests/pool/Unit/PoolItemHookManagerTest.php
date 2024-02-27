<?php

declare(strict_types=1);

namespace Allsilaevex\Pool\Test\Unit;

use PHPUnit\Framework\TestCase;
use Allsilaevex\Pool\Hook\PoolItemHook;
use PHPUnit\Framework\Attributes\CoversClass;
use Allsilaevex\Pool\Hook\PoolItemHookManager;
use Allsilaevex\Pool\PoolItemWrapperInterface;
use Allsilaevex\Pool\Hook\PoolItemHookInterface;

#[CoversClass(PoolItemHookManager::class)]
class PoolItemHookManagerTest extends TestCase
{
    public function testRun(): void
    {
        $manager = new PoolItemHookManager([
            $this->createPoolItemHook(PoolItemHook::AFTER_RETURN),
            $this->createPoolItemHook(PoolItemHook::BEFORE_BORROW),
        ]);

        $poolItemWrapper = $this->createMock(PoolItemWrapperInterface::class);
        $poolItemWrapper->expects(self::once())->method('getItem');

        $manager->run(PoolItemHook::AFTER_RETURN, $poolItemWrapper);
    }

    /**
     * @return PoolItemHookInterface<object>
     */
    protected function createPoolItemHook(PoolItemHook $hook): PoolItemHookInterface
    {
        return new /**
         * @implements PoolItemHookInterface<object>
         */ class($hook) implements PoolItemHookInterface {
            public function __construct(
                protected PoolItemHook $hook,
            ) {
            }

            public function invoke(PoolItemWrapperInterface $poolItemWrapper): void
            {
                $poolItemWrapper->getItem();
            }

            public function getHook(): PoolItemHook
            {
                return $this->hook;
            }
        };
    }
}
