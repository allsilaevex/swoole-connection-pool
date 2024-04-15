<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$cpuNum = swoole_cpu_num();

$defaultOptions = [
    'pool.enabled' => true,
    'pool.size' => 4 * $cpuNum,
    'pool.minimum_idle' => 2 * $cpuNum,
    'pool.idle_timeout_sec' => 30.0,
    'pool.max_lifetime_sec' => 300.0,
    'pool.borrowing_timeout_sec' => 1.0,
    'pool.returning_timeout_sec' => .1,
    'pool.max_item_reserving_for_update_waiting_time_sec' => .5,

    'server.host' => '0.0.0.0',
    'server.port' => 11111,
    'server.backlog' => 512,
    'server.worker_num' => 2 * $cpuNum,
    'server.dispatch_mode' => 1,
    'server.max_connection' => 512 * (2 * $cpuNum),
    'server.worker_max_concurrency' => 1024,

    'scenario.query_per_request' => 2,
    'scenario.parallel_queries_execution' => true,
    'scenario.cpu_load_per_request_min_sec' => .001,
    'scenario.cpu_load_per_request_max_sec' => .01,

    'connection.dsn' => 'mysql:host=mysql;dbname=test',
    'connection.username' => 'root',
    'connection.password' => '',
    'connection.min_delay_sec' => .0,
    'connection.max_delay_sec' => .0,
    'connection.min_query_duration_sec' => .001,
    'connection.max_query_duration_sec' => .01,
];

$argv = getopt('', array_map(static fn (string $name) => "$name::", array_keys($defaultOptions)));

$options = array_combine(array_keys($defaultOptions), array_map(static function (string $name, mixed $defaultOption) use ($argv) {
    if (!is_array($argv) || !array_key_exists($name, $argv)) {
        return $defaultOption;
    }

    return match (gettype($defaultOption)) {
        default => $defaultOption,
        'double' => (float)$argv[$name],
        'string' => (string)$argv[$name],
        'boolean' => filter_var($argv[$name], FILTER_VALIDATE_BOOLEAN),
        'integer' => (int)$argv[$name],
    };
}, array_keys($defaultOptions), $defaultOptions));

gc_disable();

(new class ($options)
{
    protected ?\Allsilaevex\Pool\PoolInterface $pool;
    protected \Swoole\Http\Server $server;

    public function __construct(
        protected readonly array $options,
    ) {
    }

    public function run(): void
    {
        $this->pool = null;
        $this->server = new \Swoole\Http\Server($this->options['server.host'], $this->options['server.port']);

        $this->server->set([
            'backlog' => $this->options['server.backlog'],
            'worker_num' => $this->options['server.worker_num'],
            'hook_flags' => SWOOLE_HOOK_ALL,
            'tcp_fastopen' => true,
            'reload_async' => false,
            'dispatch_mode' => $this->options['server.dispatch_mode'],
            'max_coroutine' => 100_000,
            'max_connection' => $this->options['server.max_connection'],
            'enable_coroutine' => true,
            'http_compression' => false,
            'open_cpu_affinity' => true,
            'worker_max_concurrency' => $this->options['server.worker_max_concurrency'],
        ]);

        $this->server->on('Request', $this->onRequest(...));
        $this->server->on('WorkerStart', $this->onWorkerStart(...));

        $this->server->start();
    }

    protected function onWorkerStart(\Swoole\Http\Server $server, int $workerId): void
    {
        $this->pool = $this->createPool();
    }

    protected function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void
    {
        if ($request->server['request_uri'] == '/shutdown') {
            $response->status(204);
            $response->end();

            $this->server->shutdown();

            return;
        }

        if ($request->server['request_uri'] == '/test') {
            if (is_null($this->pool)) {
                $response->status(500);
                $response->end();

                return;
            }

            $queryPerRequest = $this->options['scenario.query_per_request'];
            $cpuLoadPerRequestMinSec = $this->options['scenario.cpu_load_per_request_min_sec'];
            $cpuLoadPerRequestMaxSec = $this->options['scenario.cpu_load_per_request_max_sec'];

            if ($queryPerRequest == 0 && $cpuLoadPerRequestMinSec + $cpuLoadPerRequestMaxSec == .0) {
                $response->end();

                return;
            }

            $dbLoader = $this->createDbLoader();
            $cpuLoader = $this->createCpuLoader();

            $tasks = array_pad([], $queryPerRequest, $dbLoader);
            $tasks[] = $cpuLoader;

            $result = $this->batch($tasks);

            if (array_sum($result) != count($tasks)) {
                $response->status(500);
            }

            $response->end();

            return;
        }

        $response->status(404);
        $response->end();
    }

    protected function batch(array $tasks): array
    {
        if ($this->options['scenario.parallel_queries_execution']) {
            return \Swoole\Coroutine\batch($tasks);
        }

        return array_map(static fn (callable $task) => $task(), $tasks);
    }

    protected function createDbLoader(): callable
    {
        return function (): int {
            $minSec = $this->options['connection.min_query_duration_sec'];
            $maxSec = $this->options['connection.max_query_duration_sec'];

            $durationSec = random_int((int)(1000 * $minSec), (int)(1000 * $maxSec)) / 1000.0;

            try {
                $connection = $this->pool->borrow();
                $data = $connection->query("select sleep($durationSec)")->fetchColumn();
                $this->pool->return($connection);
            } catch (\Throwable $throwable) {
                echo '[error] ' . $throwable->getMessage() . PHP_EOL;
                return 0;
            }

            return 1;
        };
    }

    protected function createCpuLoader(): callable
    {
        $minSec = $this->options['scenario.cpu_load_per_request_min_sec'];
        $maxSec = $this->options['scenario.cpu_load_per_request_max_sec'];

        return static function () use ($minSec, $maxSec): int {
            $elapsedMs = 0;
            $durationMs = random_int((int)(1000 * $minSec), (int)(1000 * $maxSec));

            while (true) {
                if ($elapsedMs >= $durationMs) {
                    return 1;
                }

                $start = hrtime(true);
                $sum = array_sum(array_map(static fn ($s) => mb_strlen($s), str_split(bin2hex(random_bytes(1024)))));
                $elapsedMs += (hrtime(true) - $start) / 1e+6;
            }
        };
    }

    protected function createPool(): \Allsilaevex\Pool\PoolInterface
    {
        $connectionFactory = $this->createConnectionFactory();

        if (!$this->options['pool.enabled']) {
            return $this->createPoolMimic($connectionFactory);
        }

        return \Allsilaevex\ConnectionPool\ConnectionPoolFactory::create($this->options['pool.size'], $connectionFactory)
            ->setMinimumIdle($this->options['pool.minimum_idle'])
            ->setIdleTimeoutSec($this->options['pool.idle_timeout_sec'])
            ->setMaxLifetimeSec($this->options['pool.max_lifetime_sec'])
            ->setBorrowingTimeoutSec($this->options['pool.borrowing_timeout_sec'])
            ->setReturningTimeoutSec($this->options['pool.returning_timeout_sec'])
            ->setMaxItemReservingForUpdateWaitingTimeSec($this->options['pool.max_item_reserving_for_update_waiting_time_sec'])
            ->instantiate();
    }

    protected function createPoolMimic(\Allsilaevex\Pool\PoolItemFactoryInterface $factory): \Allsilaevex\Pool\PoolInterface
    {
        return new class ($factory) implements \Allsilaevex\Pool\PoolInterface {
            protected array $itemList;

            public function __construct(
                protected readonly \Allsilaevex\Pool\PoolItemFactoryInterface $factory,
            ) {
                $this->itemList = [];
            }

            public function __destruct()
            {
                $this->itemList = [];
            }

            public function borrow(): mixed
            {
                $cid = \Swoole\Coroutine::getCid();

                if (array_key_exists($cid, $this->itemList)) {
                    return $this->itemList[$cid];
                }

                $this->itemList[$cid] = $this->factory->create();

                if ($cid != -1) {
                    \Swoole\Coroutine::defer(function () use ($cid) {
                        unset($this->itemList[$cid], $cid);
                    });
                }

                return $this->itemList[$cid];
            }

            public function return(mixed &$poolItemRef): void
            {
                $cid = \Swoole\Coroutine::getCid();

                $poolItemRef = null;

                unset($this->itemList[$cid]);
            }

            public function stats(): array
            {
                return [];
            }
        };
    }

    protected function createConnectionFactory(): \Allsilaevex\Pool\PoolItemFactoryInterface
    {
        return new class($this->options) implements \Allsilaevex\Pool\PoolItemFactoryInterface {
            public function __construct(
                protected readonly array $options,
            ) {
            }

            public function create(): mixed
            {
                $minDelaySec = $this->options['connection.min_delay_sec'];
                $maxDelaySec = $this->options['connection.max_delay_sec'];

                try {
                    $pdo = new \PDO(
                        dsn: $this->options['connection.dsn'],
                        username: $this->options['connection.username'] ?: null,
                        password: $this->options['connection.password'] ?: null,
                        options: [
                            \PDO::ATTR_TIMEOUT => 1,
                            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        ],
                    );

                    if ($minDelaySec + $maxDelaySec > .0) {
                        $delaySec = random_int((int)(1000 * $minDelaySec), (int)(1000 * $maxDelaySec)) / 1000.0;

                        $pdo->query("select sleep($delaySec)")->fetchAll();
                    }

                    return $pdo;
                } catch (\Throwable $throwable) {
                    throw new \Allsilaevex\Pool\Exceptions\PoolItemCreationException(
                        message: $throwable->getMessage(),
                        code: $throwable->getCode(),
                        previous: $throwable,
                    );
                }
            }
        };
    }
})->run();
