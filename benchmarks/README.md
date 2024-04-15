<h1>Benchmarks</h1>

Running the script with all available parameters:

```shell
php benchmarks/runner.php \
    --pool.enabled=on \
    --pool.size=64 \
    --pool.minimum_idle=32 \
    --pool.idle_timeout_sec=30 \
    --pool.max_lifetime_sec=300 \
    --pool.borrowing_timeout_sec=1 \
    --pool.returning_timeout_sec=0.1 \
    --pool.max_item_reserving_for_update_waiting_time_sec=0.5 \
    --server.host=0.0.0.0 \
    --server.port=11111 \
    --server.backlog=512 \
    --server.worker_num=32 \
    --server.dispatch_mode=1 \
    --server.max_connection=16384 \
    --server.worker_max_concurrency=1024 \
    --scenario.query_per_request=2 \
    --scenario.parallel_queries_execution=on \
    --scenario.cpu_load_per_request_min_sec=0.001 \
    --scenario.cpu_load_per_request_max_sec=0.01 \
    --connection.dsn="mysql:host=mysql;dbname=test" \
    --connection.username="root" \
    --connection.password="" \
    --connection.min_delay_sec=0 \
    --connection.max_delay_sec=0 \
    --connection.min_query_duration_sec=0.001 \
    --connection.max_query_duration_sec=0.01
```

If any parameter isn't specified, the default value will be used.
All default values are listed at the beginning of the `benchmarks/runner.php`.

The test starts a simple HTTP server with the `/test` endpoint.
In this endpoint, the specified number of requests to the database is made.
If a non-zero interval of CPU-bound load is specified, it will be started last.

<h2>Examples</h2>

The tests demonstrate relative performance depending on presets.
Therefore, details (such as CPU characteristics or number of threads) are intentionally omitted as they are irrelevant.

All tests were run in the same environment under identical conditions using the <a href="https://github.com/wg/wrk">wrk</a>.
MySQL was used as the database.

<h3>Tests without additional CPU-bound load</h3>

Without connection pool:

```shell
php benchmarks/runner.php \
    --pool.enabled=off \
    --scenario.query_per_request=10 \
    --scenario.parallel_queries_execution=on \
    --scenario.cpu_load_per_request_min_sec=0 \
    --scenario.cpu_load_per_request_max_sec=0 \
    --connection.min_delay_sec=0 \
    --connection.max_delay_sec=0 \
    --connection.min_query_duration_sec=0.001 \
    --connection.max_query_duration_sec=0.01
```

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

Let's keep all parameters unchanged except `--pool.enabled=on`:

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

A connection pool allows you to unload system resources and efficiently handle a large number of requests.

<h3>Tests with additional CPU-bound load</h3>

Without connection pool:

```shell
php benchmarks/runner.php \
    --pool.enabled=off \
    --scenario.query_per_request=1 \
    --scenario.parallel_queries_execution=on \
    --scenario.cpu_load_per_request_min_sec=0.02 \
    --scenario.cpu_load_per_request_max_sec=0.02 \
    --connection.min_delay_sec=0 \
    --connection.max_delay_sec=0 \
    --connection.min_query_duration_sec=0.02 \
    --connection.max_query_duration_sec=0.02
```

```shell
Running 10s test @ http://172.29.0.3:11111/test
  8 threads and 16 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    44.38ms    7.88ms  81.65ms   82.62%
    Req/Sec    45.02      9.46    60.00     67.88%
  Latency Distribution
     50%   40.79ms
     75%   40.97ms
     90%   61.12ms
     99%   61.58ms
  3602 requests in 10.01s, 534.67KB read
Requests/sec:    359.81
Transfer/sec:     53.41KB
```

Note that the database query time is set to 20 ms.
Also, the CPU-bound load time is set to 20 ms.

Without a connection pool, we are guarantee a response time of at least 40 ms and longer.
Even single requests without concurrency will show the same timings.

Let's keep all parameters unchanged except `--pool.enabled=on`:

```shell
Running 10s test @ http://172.29.0.3:11111/test
  8 threads and 16 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    25.15ms    8.68ms  61.43ms   76.96%
    Req/Sec    79.70     24.93   101.00     78.25%
  Latency Distribution
     50%   20.44ms
     75%   20.62ms
     90%   40.85ms
     99%   41.14ms
  6357 requests in 10.01s, 0.92MB read
Requests/sec:    634.84
Transfer/sec:     94.23KB
```

In this case, we manage to respond to most requests within 20 ms (as expected).

<h3>Tests with special conditions</h3>

Let's consider a scenario where creating a connection takes a long time, but requests are executed quickly.
We intentionally reduce the number of idle connections in the pool before the load.

```shell
php benchmarks/runner.php \
    --pool.enabled=on \
    --pool.minimum_idle=1 \
    --scenario.query_per_request=1 \
    --scenario.parallel_queries_execution=on \
    --scenario.cpu_load_per_request_min_sec=0 \
    --scenario.cpu_load_per_request_max_sec=0 \
    --connection.min_delay_sec=0.1 \
    --connection.max_delay_sec=0.1 \
    --connection.min_query_duration_sec=0.001 \
    --connection.max_query_duration_sec=0.001
```

```shell
Running 10s test @ http://0.0.0.0:11111/test
  8 threads and 64 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     1.63ms    0.88ms  36.75ms   96.74%
    Req/Sec     5.05k   317.62     6.56k    81.02%
  Latency Distribution
     50%    1.48ms
     75%    1.68ms
     90%    1.99ms
     99%    3.82ms
  402591 requests in 10.10s, 58.36MB read
Requests/sec:  39859.03
Transfer/sec:      5.78MB
```

In this case, the pool won't wait for connections when there's a shortage but will take the first available one.
This allows for the most efficient execution of all requests even when active connections are insufficient.
