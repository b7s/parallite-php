# API Reference

Complete API documentation for Parallite PHP Client.

## Helper Functions (Recommended)

> **No Imports Required!** The `async()` and `await()` functions are available globally after
`require 'vendor/autoload.php';`

### `async(Closure $closure, ?bool $enableBenchmark = null): Promise`

Create a promise for async execution with automatic daemon management.

```php
// Use default from parallite.json
$promise = async(fn() => heavyTask());

// Force enable benchmark for this task
$promise = async(fn() => heavyTask(), enableBenchmark: true);

// Force disable benchmark for this task
$promise = async(fn() => heavyTask(), enableBenchmark: false);
```

**Parameters:**

- `$closure`: The task to execute asynchronously
- `$enableBenchmark`: Enable benchmark (null = read from `parallite.json`, true = force enable, false = force disable)

**Priority:** parameter > `parallite.json` > default (false)

**Returns:** `Promise` object with chainable methods

**Features:**

- Automatic daemon lifecycle management
- No manual setup required
- Shared client instance (efficient)
- Configurable benchmark (global or per-task)

### `await(Promise|array $promise): mixed`

Await a promise (or array of promises) to resolve and return the result(s).

```php
// Single promise
$result = await($promise);

// Array of promises (returns array of results)
$results = await([$promise1, $promise2, $promise3]);

// Mixed array (promises are resolved, other values pass through)
$results = await([
    'static' => 'value',
    'dynamic' => async(fn() => 'computed'),
]);
// ['static' => 'value', 'dynamic' => 'computed']
```

**Parameters:**

- `$promise`: Promise object, raw future array, or array containing Promise objects

**Returns:**

- Single result if given a single promise
- Array of results if given an array (promises are resolved, other values pass through)

## `Promise` Class

Promise object with chainable methods for async operations.

### `then(Closure $callback): Promise`

Register a transformation callback that runs when the chain is in a resolved state.

Based on the [Promise/A+](https://promisesaplus.com/) specification.

```php
$promise = async(fn() => 10)
    ->then(fn(int $result) => $result * 2)
    ->then(fn(int $result) => $result + 5);

$final = await($promise); // 25
```

### `catch(Closure $callback): Promise`

Handle exceptions using Promise semantics familiar from JavaScript: as soon as an error occurs, execution jumps to the
next registered `catch()` handler. If the handler resolves successfully, the chain continues with the following `then()`
callbacks.

```php
$promise = async(fn() => throw new RuntimeException('Failure'))
    ->then(fn() => 'never executed')
    ->catch(fn(Throwable $e) => 'Recovered: ' . $e->getMessage())
    ->then(fn(string $message) => strtoupper($message));

$final = await($promise); // RECOVERED: FAILURE
```

Multiple `catch()` handlers can be chained; each one is tried until one returns without throwing.

### `finally(Closure $callback): Promise`

Register callbacks that always run after the chain settles, regardless of success or failure.

```php
$log = [];

$promise = async(fn() => 42)
    ->finally(fn() => $log[] = 'cleanup');

await($promise);
// $log === ['cleanup']
```

`finally()` callbacks do not receive the resolved value and cannot modify the chain result.

### `resolve(): mixed`

Manually resolve the promise.

```php
$result = $promise->resolve();
```

### `getBenchmark(): ?BenchmarkData`

Get benchmark data if benchmark mode was enabled.

```php
$benchmark = $promise->getBenchmark();
if ($benchmark) {
    echo "Execution: {$benchmark->executionTimeMs}ms\n";
    echo "Memory Δ: {$benchmark->memoryDeltaMb}MB\n";
    echo "Peak: {$benchmark->memoryPeakMb}MB\n";
    echo "CPU: {$benchmark->cpuTimeMs}ms\n";
}
```

## `ParalliteClient` (Advanced)

For advanced use cases requiring manual control.

### Constructor

```php
public function __construct(
    string $socketPath = '',
    bool $autoManageDaemon = false,
    ?string $projectRoot = null,
    ?bool $enableBenchmark = null
)
```

**Parameters:**

- `$socketPath`: Custom socket path (default: `/tmp/parallite.sock`)
- `$autoManageDaemon`: Automatically start/stop daemon (default: `false`)
- `$projectRoot`: Custom project root for config resolution (default: auto-detected)
- `$enableBenchmark`: Enable benchmark mode globally (default: from config or `false`)

### Methods

#### `promise(Closure $closure): Promise`

Create a Promise with chaining support.

```php
$promise = $client->promise(fn() => task());
```

#### `async(Closure $closure): array`

Lower-level API returning raw futures.

```php
$future = $client->async(fn() => task());
$result = $client->await($future);
```

#### `await(Promise|array $future): mixed`

Await a Promise or future.

```php
$result = $client->await($promise);
```

#### `awaitAll(array $closures): array`

Batch operation to await multiple closures.

```php
$results = $client->awaitAll([
    fn() => task1(),
    fn() => task2(),
]);
```

#### `awaitMultiple(array $promises): array`

Await multiple promises/futures in parallel. Non-promise values pass through unchanged.

```php
$results = $client->awaitMultiple([
    'static' => 'value',
    'promise1' => $client->promise(fn() => task1()),
    'promise2' => $client->promise(fn() => task2()),
]);
// ['static' => 'value', 'promise1' => result1, 'promise2' => result2]
```

#### `enableBenchmark(): void`

Enable benchmark mode for all subsequent tasks.

```php
$client->enableBenchmark();
```

#### `disableBenchmark(): void`

Disable benchmark mode.

```php
$client->disableBenchmark();
```

#### `stopDaemon(): void`

Manually stop the daemon (only if `autoManageDaemon` is enabled).

```php
$client->stopDaemon();
```

## `BenchmarkData` Class

Performance metrics for executed tasks.

### Properties

```php
public readonly float $executionTimeMs;  // Total execution time in milliseconds
public readonly float $memoryDeltaMb;    // Memory change during task (MB)
public readonly float $memoryPeakMb;     // Peak memory usage (MB)
public readonly float $cpuTimeMs;        // CPU time (user + system) in milliseconds
```

### Methods

#### `__toString(): string`

Get formatted benchmark string.

```php
echo $benchmark; // "Execution: 123.45ms | Memory Δ: 0.50MB | Peak: 5.00MB | CPU: 120.80ms"
```

## Advanced Usage Examples

### Custom Socket Path

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient('/var/run/my-app/parallite.sock');
```

### With Custom Project Root

```php
$client = new ParalliteClient(
    '/tmp/parallite.sock',
    autoManageDaemon: true,
    projectRoot: '/path/to/project'
);
```

### Manual Daemon Control

```php
// Start daemon with specific configuration
$client = new ParalliteClient('/tmp/parallite.sock', autoManageDaemon: true);

// Do work...
$result = $client->await($client->async(fn() => 'work'));

// Stop daemon manually
$client->stopDaemon();
```

### Using ParalliteClient Directly

For advanced use cases where you need manual daemon control or custom socket paths:

```php
use Parallite\ParalliteClient;

// Manual daemon management
$client = new ParalliteClient('/tmp/parallite.sock', autoManageDaemon: true);

// Lower-level API (returns raw futures)
$future = $client->async(fn() => 'task');
$result = $client->await($future);

// Or use promise() for chaining
$promise = $client->promise(fn() => 'task')
    ->then(fn($r) => strtoupper($r));
$result = $client->await($promise);

// Batch operations
$results = $client->awaitAll([
    fn() => task1(),
    fn() => task2(),
    fn() => task3(),
]);
```

**When to use `ParalliteClient` directly:**

- You need custom socket paths
- You want manual daemon lifecycle control
- You're integrating with existing code that manages daemons
- You need the `awaitAll()` batch method

**For most use cases, use the `async()` and `await()` helpers instead.**

> How to handle complex data structures, see:
> **[Complex Data Handling](docs/complex-data-handling.md)**
