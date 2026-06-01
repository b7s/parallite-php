# API Reference

Complete API documentation for Parallite PHP.

## Helper Functions (Recommended)

> **No Imports Required!** The `async()` and `await()` functions are available globally after
> `require 'vendor/autoload.php';`

### `async(Closure $closure, ?bool $enableBenchmark = null): Promise`

Create a promise for parallel execution via `pcntl_fork`.

```php
// Use default from parallite.json
$promise = async(fn() => heavyTask());

// Force enable benchmark for this task
$promise = async(fn() => heavyTask(), enableBenchmark: true);

// Force disable benchmark for this task
$promise = async(fn() => heavyTask(), enableBenchmark: false);
```

**Parameters:**

- `$closure`: The task to execute in a forked process
- `$enableBenchmark`: Enable benchmark (null = read from `parallite.json`, true = force enable, false = force disable)

**Priority:** parameter > `parallite.json` > default (false)

**Returns:** `Promise` object with chainable methods

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

```php
$promise = async(fn() => 10)
    ->then(fn(int $result) => $result * 2)
    ->then(fn(int $result) => $result + 5);

$final = await($promise); // 25
```

### `catch(Closure $callback): Promise`

Handle exceptions using Promise semantics: as soon as an error occurs, execution jumps to the next registered `catch()` handler. If the handler resolves successfully, the chain continues with the following `then()` callbacks.

```php
$promise = async(fn() => throw new RuntimeException('Failure'))
    ->then(fn() => 'never executed')
    ->catch(fn(Throwable $e) => 'Recovered: ' . $e->getMessage())
    ->then(fn(string $message) => strtoupper($message));

$final = await($promise); // RECOVERED: FAILURE
```

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
    bool $enableBenchmark = false,
    bool $useFork = true,
)
```

**Parameters:**

- `$enableBenchmark`: Enable benchmark mode globally (default: `false`)
- `$useFork`: Use fork mode when available (default: `true`). Falls back to sequential when `pcntl` is not available.

### Methods

#### `promise(Closure $closure): Promise`

Create a Promise with chaining support.

```php
$promise = $client->promise(fn() => task());
```

#### `async(Closure $closure): array`

Lower-level API returning raw fork handles.

```php
$future = $client->async(fn() => task());
$result = $client->await($future);
```

#### `await(array|Promise|null $future): mixed`

Await a Promise or fork handle.

```php
$result = $client->await($promise);
```

#### `awaitAll(array $closures): array`

Batch operation — fork all closures, then await all results.

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

#### `enableBenchmark(): self`

Enable benchmark mode for all subsequent tasks.

```php
$client->enableBenchmark();
```

#### `disableBenchmark(): self`

Disable benchmark mode.

```php
$client->disableBenchmark();
```

#### `isForkMode(): bool`

Check if the client is running in fork mode (parallel) or sequential mode.

```php
if ($client->isForkMode()) {
    echo 'Running in parallel!';
}
```

## `BenchmarkData` Class

Performance metrics for executed tasks.

### Properties

```php
public readonly float $executionTimeMs; // Total execution time in milliseconds
public readonly float $memoryDeltaMb;   // Memory change during task (MB)
public readonly float $memoryPeakMb;    // Peak memory usage (MB)
public readonly float $cpuTimeMs;       // CPU time (user + system) in milliseconds
```

### Methods

#### `__toString(): string`

Get formatted benchmark string.

```php
echo $benchmark; // "Execution: 123.45ms | Memory Δ: 0.50MB | Peak: 5.00MB | CPU: 120.80ms"
```
