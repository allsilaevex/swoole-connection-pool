<h1 align="center">Swoole Connection Pool</h1>

<p align="center">
    <strong>A solid, flexible and high-performance Swoole based connection pool.</strong>
</p>

<p align="center">
    <a href="https://php.net"><img src="https://img.shields.io/packagist/php-v/allsilaevex/swoole-connection-pool.svg?style=flat-square&colorB=%238892BF" alt="PHP Programming Language"></a>
    <a href="https://packagist.org/packages/allsilaevex/swoole-connection-pool"><img src="https://img.shields.io/packagist/v/allsilaevex/swoole-connection-pool.svg?style=flat-square&label=packagist" alt="Download Package"></a>
    <a href="https://github.com/allsilaevex/swoole-connection-pool/actions/workflows/continuous-integration.yaml"><img src="https://github.com/allsilaevex/swoole-connection-pool/actions/workflows/continuous-integration.yaml/badge.svg" alt="CI Status"></a>
    <a href="https://app.codecov.io/gh/allsilaevex/swoole-connection-pool"><img src="https://img.shields.io/codecov/c/github/allsilaevex/swoole-connection-pool?label=codecov&logo=codecov&style=flat-square" alt="Codecov Code Coverage"></a>
    <a href="https://shepherd.dev/github/allsilaevex/swoole-connection-pool"><img src="https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fshepherd.dev%2Fgithub%2Fallsilaevex%2Fswoole-connection-pool%2Fcoverage" alt="Psalm Type Coverage"></a>
    <a href="https://phpstan.org/"><img src="https://img.shields.io/badge/PHPStan_level-9-brightgreen.svg?style=flat" alt="PHPStan level"></a>
    <a href="https://psalm.dev/"><img src="https://shepherd.dev/github/allsilaevex/swoole-connection-pool/level.svg" alt="Psalm level"></a>
    <a href="https://choosealicense.com/licenses/mit/"><img src="https://poser.pugx.org/allsilaevex/swoole-connection-pool/license" alt="License"></a>
</p>

<h2>⚙️ Installation</h2>

```bash
composer require allsilaevex/swoole-connection-pool
```

<h3>Requirements</h3>

* <a href="https://www.php.net/manual/en/install.php">PHP 8.2.0</a> or later
* <a href="https://github.com/swoole/swoole-src">Swoole 5.1.0</a> or later

> [!WARNING]
> Pool has not been tested with `swoole.enable_preemptive_scheduler = 1`. Use at your own risk!

<h2>⚡️ Quickstart</h2>

This example demonstrates the creation of a simple pool of connections to the MySQL database.

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

\Swoole\Coroutine\run(static function () {
    $connectionPoolFactory = \Allsilaevex\ConnectionPool\ConnectionPoolFactory::create(
        size: 2,
        factory: new \Allsilaevex\ConnectionPool\ConnectionFactories\PDOConnectionFactory(
            dsn: 'mysql:host=0.0.0.0;port=3306;dbname=default',
            username: 'root',
            password: 'root',
        ),
    );

    $pool = $connectionPoolFactory->instantiate();

    \Swoole\Coroutine\parallel(n: 4, fn: static function () use ($pool) {
        /** @var \PDO $connection */
        $connection = $pool->borrow();

        $result = $connection->query('select 42')->fetchColumn();

        var_dump($result);
    });
});
```

<h2>✨ Features</h2>

* High-performance even in unusual cases (see <a href="#benchmarks">Benchmarks</a>)
* Handling connection failure and self-recovery
* Doesn't burden the garbage collector
* Coverage by static analyzers (PHPStan, Psalm) and support generics
* Out of the box connection pool provides:
  * load-dependent resizing number of connections
  * reconnection for long-lived connections
  * leaked connection detection
  * support lifetime hooks for connections
* Metrics that can be easily stored into Prometheus and used for analysis

<h2>❓ Why should I use a connection pool?</h2>

The most obvious reason: connection pool saves time by not establishing a new connection for each request.

A less obvious reason lies in the way Swoole and coroutines work.
By design, it's not possible to use the same connection simultaneously in two different coroutines,
which means you must create a separate connection for each coroutine.
This adds extra overhead compared to using a single connection sequentially.
It can also lead to uncontrolled growth in the number of connections.

And the least obvious issue: slowdowns due to multiple context switches during execution.
Context switching occurs during IO operations, including establishing a connection.
Thus, the execution flow of the following code may not be obvious:

```php
<?php

declare(strict_types=1);

\Swoole\Coroutine\run(static function () {
    \Swoole\Coroutine\go(static function () {
        echo '1' . PHP_EOL;

        $pdo = new \PDO(
            dsn: 'mysql:host=0.0.0.0;port=3306;dbname=default',
            username: 'root',
            password: 'root',
        );

        echo '2' . PHP_EOL;

        $pdo->query('select 1')->fetchAll();

        echo '4' . PHP_EOL;
    });

    \Swoole\Coroutine\go(static function () {
        echo '3' . PHP_EOL;
    });
});

// output:
// 1
// 3
// 2
// 4
```

What happens if a CPU-bound load appears in the second coroutine?
Since coroutines are executed within the same process, they cannot parallelize the execution of CPU-bound code.
Therefore, if one coroutine is executing PHP code, the others will wait.
So, the execution of query will be deferred until the second coroutine
finishes its work and returns control back to the first coroutine.

<h2 id="benchmarks">🚀 Benchmarks</h2>

Without connection pool:

```shell
Running 10s test @ http://0.0.0.0:11111/test
  8 threads and 64 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency   238.48ms  301.25ms   3.06s    85.59%
    Req/Sec    55.56     54.91   353.00     92.78%
  Latency Distribution
     50%  103.29ms
     75%  328.69ms
     90%  691.90ms
     99%    1.12s
  4280 requests in 10.02s, 667.52KB read
  Non-2xx or 3xx responses: 1736
Requests/sec:    426.94
Transfer/sec:     66.59KB
```

With connection pool:

```shell
Running 10s test @ http://0.0.0.0:11111/test
  8 threads and 64 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    11.33ms    1.72ms  38.38ms   82.34%
    Req/Sec   709.47     34.52   777.00     73.12%
  Latency Distribution
     50%   11.08ms
     75%   11.86ms
     90%   12.97ms
     99%   18.03ms
  56525 requests in 10.02s, 8.19MB read
Requests/sec:   5642.51
Transfer/sec:    837.56KB
```

Read more about the tests in the <a href="./benchmarks/README.md">document</a>.

<h2>🔧 Configuration</h2>

For simplifying configuration and creating a pool, there's the `ConnectionPoolFactory`.
The factory comes with default settings, but it's <strong>highly recommended</strong> to customize the parameters
according to your specific needs.

The following example demonstrates the configuration options:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

\Swoole\Coroutine\run(static function () {
    $connectionPoolFactory = \Allsilaevex\ConnectionPool\ConnectionPoolFactory::create(
        // Maximum number of connections in the pool
        size: 4,

        // A trivial PDO connection factory
        // For other connections, you need to define factory that implements \Allsilaevex\Pool\PoolItemFactoryInterface
        factory: new \Allsilaevex\ConnectionPool\ConnectionFactories\PDOConnectionFactory(
            dsn: 'mysql:host=0.0.0.0;port=3306;dbname=default',
            username: 'root',
            password: 'root',
        ),
    );

    // The minimum number of connections that the pool will maintain
    // Setting it to 0 means the pool will create connections only when needed
    // Setting it to MAX means the pool will always keep exactly MAX connections
    $connectionPoolFactory->setMinimumIdle(2);

    // The time during which connections can remain idle in the pool
    // After the timeout expires, connections will be destroyed until the pool size reaches the minimumIdle value
    $connectionPoolFactory->setIdleTimeoutSec(15.0);

    // Maximum connection lifetime
    // When setting this, it's recommended to consider database limits and infrastructure constraints.
    $connectionPoolFactory->setMaxLifetimeSec(60.0);

    // Maximum waiting time for reserving a connection for re-creation (see maxLifetimeSec)
    // This can be useful when all connections in the pool are constantly occupied for a long time
    // Setting it to .0 means there will be no waiting during reservation
    $connectionPoolFactory->setMaxItemReservingForUpdateWaitingTimeSec(.5);

    // The maximum waiting time for a connection from the pool during a borrow attempt
    // After this time expires, an \Allsilaevex\Pool\Exceptions\BorrowTimeoutException will be thrown
    $connectionPoolFactory->setBorrowingTimeoutSec(.1);

    // The maximum waiting time for returning a connection to the pool
    // After this time expires, the connection will be destroyed
    $connectionPoolFactory->setReturningTimeoutSec(.01);

    // If true, then connection will automatically return to the pool after the coroutine in which it was borrowed finishes execution
    // Auto return can only work with coroutine binding!
    $connectionPoolFactory->setAutoReturn(true);

    // If true, then when borrowing a connection from the pool for one coroutine, the same connection will always be returned
    $connectionPoolFactory->setBindToCoroutine(true);

    // A logger is used to signal abnormal situations
    // Any logger that implements \Psr\Log\LoggerInterface is allowed
    $connectionPoolFactory->setLogger(logger: new \Psr\Log\NullLogger());

    // Maximum time that a connection can be out of the pool without leak warnings
    $connectionPoolFactory->setLeakDetectionThresholdSec(1.0);

    // Allows adding a KeepaliveChecker that must implement the \Allsilaevex\ConnectionPool\KeepaliveCheckerInterface
    // This checker will be called at a specified interval and can trigger connection re-creation (if it returns false)
    $connectionPoolFactory->addKeepaliveChecker(
        new class () implements \Allsilaevex\ConnectionPool\KeepaliveCheckerInterface {
            public function check(mixed $connection): bool
            {
                try {
                    $connection->getAttribute(\PDO::ATTR_SERVER_INFO);
                } catch (\Throwable) {
                    return false;
                }

                return true;
            }

            public function getIntervalSec(): float
            {
                return 60.0;
            }
        },
    );

    // Allows adding a ConnectionChecker that must be callable
    // This checker will be called before connection borrowing and can trigger connection re-creation (if it returns false)
    $connectionPoolFactory->addConnectionChecker(
        static function (\PDO $connection): bool {
            try {
                return !$connection->inTransaction();
            } catch (\Throwable) {
                return false;
            }
        },
    );

    // You can specify a pool name for identifying logs, metrics, etc.
    // Or leave the field empty and the name will be generated based on the factory class
    $pool = $connectionPoolFactory->instantiate(name: 'my-pool');

    \Swoole\Coroutine\parallel(n: 8, fn: static function () use ($pool) {
        /** @var \PDO $connection */
        $connection = $pool->borrow();

        $result = $connection->query('select 42')->fetchColumn();

        var_dump($result);
    });
});
```

<h2>License</h2>

<a href="./LICENSE">MIT</a>
