<div align="center">

<img src="art/parallite-logo.webp" alt="Parallite Logo" width="128">
<h1>Parallite {PHP Client}</h1>

</div>

[![Latest Version](https://img.shields.io/packagist/v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![PHP Version](https://img.shields.io/packagist/php-v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![License](https://img.shields.io/packagist/l/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)

**Execute PHP closures in true parallel** - A standalone PHP client for [Parallite](https://github.com/b7s/parallite),
enabling real parallel execution of PHP code without the limitations of traditional PHP concurrency.

## ✨ Features

- 🚀 **True Parallel Execution** - Execute multiple PHP closures simultaneously
- ⚡ **Binary MessagePack Transport** - Replaces JSON for ultra-fast daemon communication
- 🔄 **Promise Chaining** - Chainable `then()`, `catch()`, and `finally()` methods
- 🎯 **Simple async/await API** - Familiar Promise-like interface
- 🌍 **Cross-platform** - Works on Windows, Linux and macOS
- ⚡ **Zero Configuration** - Works out of the box
- 🎯 **Automatic Daemon Management** - Optional auto-start/stop of Parallite daemon
- 📦 **Standalone** - No framework dependencies required
- 🔧 **Configurable** - Fine-tune worker pools, timeouts, and more
- 📝 **Configuration** - Load Daemon configuration and PHP required files from parallite.json (optional)
- 📚 **Documentation** - Comprehensive API reference and examples
- 🧪  **Tests** - Full Testing Suite, with real-world examples

## 📋 Requirements

- PHP 8.3 or higher
- ext-msgpack
- ext-sockets
- ext-zip
- Composer 2+

## 📦 Installation

Install via Composer:

```bash
composer require parallite/parallite-php
```

After installation, install the Parallite binary. This little Go binary serves as the **daemon orchestrator**, managing
worker processes and coordinating parallel task execution ([learn more](https://github.com/b7s/parallite)).

### Option 1: Automatic Installation (Recommended)

Add this two PHP commands to your project's `composer.json`:

```json
{
  "scripts": {
    "post-install-cmd": [
      "@php vendor/parallite/parallite-php/bin/parallite-install"
    ],
    "post-update-cmd": [
      "@php vendor/parallite/parallite-php/bin/parallite-update"
    ]
  }
}
```

> **Tip**: See [`composer.json.example`](composer.json.example) for a complete example.

Then run:

```bash
composer install

# or update
composer update
```

The binary will be automatically installed at `vendor/bin/parallite`.

### Option 2: One-Time Installation

Run the **installer** script once:

```bash
php vendor/parallite/parallite-php/bin/parallite-install
```

#### Available flags

- `--force`: reinstall even if the binary already exists.
- `--version=X.Y.Z`: install a specific release (accepts `1.2.3` or `v1.2.3`).

Examples:

```bash
php vendor/parallite/parallite-php/bin/parallite-install --force
php vendor/parallite/parallite-php/bin/parallite-install --version=1.2.3
```

If you want to **update** the binary:

```bash
php vendor/parallite/parallite-php/bin/parallite-update
```

#### Update command flags

- `--force`: allow updates across major versions.
- `--version=X.Y.Z`: install a specific release directly.

Examples:

```bash
php vendor/parallite/parallite-php/bin/parallite-update --force
php vendor/parallite/parallite-php/bin/parallite-update --version=1.2.3
```

## ⚙️ Configuration (Important!)

After installation, you can customize Parallite's behavior by creating a `parallite.json` file in your project root:

```json
{
  "php_includes": [
    "bootstrap/app.php",
    "/absolute/path/to/config.php"
  ],
  "enable_benchmark": false,
  "go_overrides": {
    "timeout_ms": 30000,
    "fixed_workers": 0,
    "prefix_name": "parallite_worker",
    "fail_mode": "continue"
  }
}
```

### Quick Configuration Summary

- **`php_includes`**: Files to load in worker processes (supports relative and absolute paths)
- **`enable_benchmark`**: Enable performance metrics globally
- **`go_overrides`**: Daemon settings (timeout, workers, etc.)

> **📖 See [Detailed Configuration](#-detailed-configuration) section below for complete documentation.**

## 🚀 Quick Start

Just use the `async()` and `await()` functions - no setup or imports required!

```php
<?php

require 'vendor/autoload.php';

// No imports needed - functions are available globally!

// Basic usage
$result = await(async(fn() => 'Hello World'));
echo $result; // Hello World

// With promise chaining
$result = await(
    async(fn() => 1 + 2)
        ->then(fn($n) => $n * 2)
        ->then(fn($n) => $n + 5)
);
echo $result; // 11

// Parallel execution
$p1 = async(fn() => sleep(1) && 'Task 1');
$p2 = async(fn() => sleep(1) && 'Task 2');
$p3 = async(fn() => sleep(1) && 'Task 3');

await([$p1, $p2, $p3]);
// Total time: ~1s (parallel) instead of 3s (sequential)

// Error handling
$result = await(
    async(function () {
        throw new Exception('Oops!');
    })->catch(fn($e) => 'Caught: ' . $e->getMessage())
);
echo $result; // Caught: Task failed: Oops!
```

**That's it!** The daemon is automatically managed - no manual setup or imports needed!

## ⚡ MessagePack Transport

Parallite now (v2+) communicates with the Go daemon using **MessagePack** instead of JSON. This binary protocol
delivers:

- **2-5x faster** encoding/decoding compared to JSON
- **30-50% smaller** payloads, reducing I/O overhead
- **40-50% lower** CPU usage in high-throughput workloads
- Native binary support without base64 conversions

The PHP client ships with [`rybakit/msgpack.php`](https://github.com/rybakit/msgpack.php) and the daemon uses [
`vmihailenco/msgpack`](https://github.com/vmihailenco/msgpack), guaranteeing full compatibility. Update your daemon to
the latest release to benefit from the new protocol.

## 📚 Usage Examples

### Basic Promise Chaining

```php
// Simple chaining
$result = await(
    async(fn() => 1 + 2)
        ->then(fn($n) => $n * 2)
        ->then(fn($n) => $n + 5)
);
echo $result; // 11
```

### Error Handling

```php
$result = await(
    async(function () {
        throw new Exception('Error');
    })->catch(fn(Throwable $e) => 'Rescued: ' . $e->getMessage())
);

echo $result; // "Rescued: Task failed: Error"
```

### Using Finally

```php
$result = await(
    async(fn() => 'success')
        ->then(fn($r) => strtoupper($r))
        ->finally(fn() => echo "Cleanup!\n")
);

echo $result; // Prints "Cleanup!" then "SUCCESS"
```

### Parallel Execution

```php
$start = microtime(true);

// Create multiple promises
$p1 = async(fn() => sleep(1) && 'Task 1');
$p2 = async(fn() => sleep(1) && 'Task 2');
$p3 = async(fn() => sleep(1) && 'Task 3');

// Option 1: Await individually
$results = [await($p1), await($p2), await($p3)];

// Option 2: Await array of promises (cleaner)
$results = await([$p1, $p2, $p3]);

$duration = microtime(true) - $start;
// Duration: ~1s (parallel) instead of 3s (sequential)
```

### Working with Data

```php
$numbers = range(1, 10);
$promises = [];

foreach ($numbers as $n) {
    $promises[] = async(fn() => $n * $n);
}

// Option 1: Using array_map
$squares = array_map(fn($p) => await($p), $promises);

// Option 2: Using await with array (simpler)
$squares = await($promises);

print_r($squares); // [1, 4, 9, 16, 25, 36, 49, 64, 81, 100]
```

### Real-World Example: Parallel API Calls

```php
$start = microtime(true);

// Fetch data from multiple APIs in parallel
$promises = [
    'users' => async(fn() => file_get_contents('https://api.example.com/users')),
    'posts' => async(fn() => file_get_contents('https://api.example.com/posts')),
    'comments' => async(fn() => file_get_contents('https://api.example.com/comments')),
];

// Await all at once
$data = await($promises);

$duration = microtime(true) - $start;
echo "Fetched 3 APIs in {$duration}s (would be 3x slower if sequential)\n";

// Access results
$users = $data['users'];
$posts = $data['posts'];
$comments = $data['comments'];
```

### Complex Data Transformation

```php
$result = await(
    async(fn() => ['name' => 'john', 'age' => 25])
        ->then(fn($data) => array_merge($data, ['name' => strtoupper($data['name'])]))
        ->then(fn($data) => array_merge($data, ['age' => $data['age'] + 5]))
        ->then(fn($data) => json_encode($data))
);

echo $result; // {"name":"JOHN","age":30}

## ⚙️ Detailed Configuration

This section provides comprehensive documentation for all configuration options available in `parallite.json`.

### Configuration File Structure

Create a `parallite.json` file in your project root:

```json
{
  "php_includes": [],
  "enable_benchmark": false,
  "go_overrides": {
    "timeout_ms": 30000,
    "fixed_workers": 0,
    "prefix_name": "parallite_worker",
    "fail_mode": "continue"
  }
}
```

### Laravel:

Create a dedicated bootstrap file (`bootstrap/parallite.php`) that:

- Loads the Composer autoloader
- Bootstraps the Laravel application using the Console Kernel
- Makes the Laravel application available without executing HTTP lifecycle

#### See the code of [bootstrap/parallite.php](examples/boot-laravel-app.php).

Include the Parallite bootstrap in the `parallite.json` file at the root of your project:

```json
{
  "php_includes": [
    "bootstrap/parallite.php"
  ],
  ...
}
```

> Don't use the HTTP kernel, which executes the full HTTP request lifecycle including:
> - Capturing HTTP requests (Request::capture())
> - Sending HTTP responses (->send())
> - Terminating the application ($kernel->terminate())
>
> This caused the PHP worker process to die prematurely before sending responses back to the Go daemon.

(Maybe other frameworks need a similar solution)

### PHP Settings

#### `php_includes` (array)

PHP files to include in worker processes before executing tasks. Useful for loading bootstrap files, configuration, or
custom autoloaders.

**Supports both relative and absolute paths:**

- **Relative paths**: Resolved relative to your project root
- **Absolute paths**: Used as-is (starts with `/` on Linux/macOS or has `:` as second character on Windows like `C:\`)

**Examples:**

```json
{
  "php_includes": [
    "bootstrap/app.php",
    "config/database.php",
    "/var/www/shared/config.php",
    "C:\\Projects\\shared\\helpers.php"
  ]
}
```

**Use cases:**

- Load framework bootstrap files (Laravel, Symfony, etc.)
- Initialize database connections
- Register custom autoloaders
- Load environment-specific configuration
- Include helper functions or constants

**Default:** `[]` (empty array)

#### `enable_benchmark` (bool)

Enable benchmark mode globally to collect performance metrics for all tasks.

- When `true`, all tasks will include benchmark data unless explicitly disabled
- Can be overridden per-task using the `$enableBenchmark` parameter in `async()`
- See [Benchmark Mode](#-benchmark-mode) section for detailed usage

**Default:** `false`

### Go Daemon Settings (`go_overrides`)

These settings control the behavior of the Parallite daemon (the Go binary that manages worker processes).

#### `timeout_ms` (int)

Maximum time in milliseconds that a task can run before being terminated.

**Default:** `30000` (30 seconds)

**Examples:**

Set timeout to 60 seconds:

```json
{
  "go_overrides": {
    "timeout_ms": 60000
  }
}
```

#### `fixed_workers` (int)

Number of worker processes to maintain in the pool.

- `0` (default): Auto-scale based on CPU cores
- `> 0`: Fixed number of workers

**Default:** `0` (auto)

**Examples:**

Always maintain 4 workersAlways maintain 4 workers:

```json
{
  "go_overrides": {
    "fixed_workers": 4
  }
}
```

#### `prefix_name` (string)

Prefix for worker process names. Useful for identifying Parallite workers in process lists.

**Default:** `"parallite_worker"`

**Examples:**

```json
{
  "go_overrides": {
    "prefix_name": "myapp_worker"
    // Workers will be named: myapp_worker_1, myapp_worker_2, etc.
  }
}
```

#### `fail_mode` (string)

How the daemon should handle task failures.

- `"continue"` (default): Continue processing other tasks even if one fails
- `"stop"`: Stop the daemon if any task fails

**Default:** `"continue"`

**Examples:**

```json
{
  "go_overrides": {
    "fail_mode": "stop"
    // Stop daemon on first failure (useful for critical tasks)
  }
}
```

### Complete Configuration Example

```json
{
  "php_includes": [
    "bootstrap/app.php",
    "config/database.php",
    "/var/www/shared/helpers.php"
  ],
  "enable_benchmark": true,
  "go_overrides": {
    "timeout_ms": 60000,
    "fixed_workers": 4,
    "prefix_name": "myapp_worker",
    "fail_mode": "continue"
  }
}
```

## 🎯 API Reference

### Helper Functions (Recommended)

> **No Imports Required!** The `async()` and `await()` functions are available globally after
`require 'vendor/autoload.php';`

#### `async(Closure $closure, ?bool $enableBenchmark = null): Promise`

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

#### `await(Promise|array $promise): mixed`

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

### `Promise` Class

Promise object with chainable methods for async operations.

#### `then(Closure $callback): Promise`

Chain a transformation callback.

```php
$promise->then(fn($result) => $result * 2);
```

#### `catch(Closure $callback): Promise`

Handle exceptions.

```php
$promise->catch(fn(Throwable $e) => 'Error: ' . $e->getMessage());
```

#### `finally(Closure $callback): Promise`

Execute cleanup code regardless of success/failure.

```php
$promise->finally(fn() => cleanup());
```

#### `resolve(): mixed`

Manually resolve the promise.

```php
$result = $promise->resolve();
```

#### `getBenchmark(): ?BenchmarkData`

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

## 📊 Benchmark Mode

Parallite can collect detailed performance metrics for each task when benchmark mode is enabled.

### Enabling Benchmark Mode

**Priority:** Function parameter > `parallite.json` > Default (false)

**Option 1: Global configuration (recommended for development)**

Edit `parallite.json`:

```json
{
  "enable_benchmark": true
}
```

All tasks will include benchmark data:

```php
$promise = async(fn() => task());  // Benchmark enabled from config
$result = await($promise);
$benchmark = $promise->getBenchmark();
```

**Option 2: Per-task override**

```php
// Force enable for specific task (overrides config)
$promise = async(fn() => heavyTask(), enableBenchmark: true);

// Force disable for specific task (overrides config)
$promise = async(fn() => lightTask(), enableBenchmark: false);

// Use config default
$promise = async(fn() => normalTask());  // null = read from parallite.json
```

**Option 3: Using ParalliteClient directly**

```php
use Parallite\ParalliteClient;

// Enable globally in constructor
$client = new ParalliteClient(
    autoManageDaemon: true,
    enableBenchmark: true
);

// Or enable after creation
$client = new ParalliteClient();
$client->enableBenchmark();
```

### Benchmark Data Structure

When benchmark mode is enabled, the daemon returns additional performance metrics (shown here as a PHP array for
readability—the on-the-wire format is MessagePack):

```php
[
  'ok' => true,
  'result' => '...',
  'task_id' => '...',
  'benchmark' => [
    'execution_time_ms' => 123.456,
    'memory_delta_mb' => 0.5,
    'memory_peak_mb' => 5.0,
    'cpu_time_ms' => 120.8,
  ],
]
```

**Fields:**

- `execution_time_ms`: Total execution time in milliseconds (always accurate)
- `memory_delta_mb`: Memory change during task execution in MB (may be zero for tasks with automatic cleanup)
- `memory_peak_mb`: Peak memory usage during task in MB (may be zero for small/fast tasks)
- `cpu_time_ms`: Total CPU time (user + system) in milliseconds (always accurate)

> **Note:** Memory metrics may show zero for tasks where PHP automatically frees memory. See "Understanding Memory
> Metrics" section for details.

### Accessing Benchmark Data

**Simple per-task benchmark (recommended):**

```php
// Enable benchmark for specific task
$promise = async(function() {
    sleep(1);
    return 'Heavy task completed';
}, enableBenchmark: true);

$result = await($promise);

// Get benchmark data
$benchmark = $promise->getBenchmark();
if ($benchmark) {
    echo "Execution time: {$benchmark->executionTimeMs}ms\n";
    echo "Memory delta: {$benchmark->memoryDeltaMb}MB\n";
    echo "Memory peak: {$benchmark->memoryPeakMb}MB\n";
    echo "CPU time: {$benchmark->cpuTimeMs}ms\n";
    
    // Or use the formatted string
    echo $benchmark; // "Execution: 123.45ms | Memory Δ: 0.50MB | Peak: 5.00MB | CPU: 120.80ms"
}
```

**Global benchmark mode:**

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient(enableBenchmark: true);

// All tasks will include benchmark data
$promise = $client->promise(fn() => heavyTask());
$result = await($promise);

$benchmark = $promise->getBenchmark();
if ($benchmark) {
    echo "Execution: {$benchmark->executionTimeMs}ms\n";
}
```

### Example: Performance Benchmark

See `examples/performance-benchmark.php` for a complete example that:

- Measures execution time, memory usage, and CPU time
- Tests different workload types (light, CPU-intensive, mixed, stress)
- Validates parallel execution
- Provides detailed performance metrics

```bash
php examples/performance-benchmark.php
```

**Note:** Benchmark mode adds minimal overhead but should only be enabled when you need performance metrics.

### Understanding Memory Metrics

**Why is `memory_delta_mb` often zero?**

The PHP worker is a **persistent process** that executes multiple tasks in a loop. Memory behavior:

1. **Before task**: Worker is already "warm" with all infrastructure loaded
2. **During task**: Variables are created in the closure scope
3. **After task**: PHP automatically frees variables when they go out of scope
4. **Result**: Memory delta ≈ 0 for most tasks

**Memory metrics reliability:**

- ✅ `execution_time_ms`: Always accurate
- ✅ `cpu_time_ms`: Always accurate
- ⚠️ `memory_delta_mb`: May be zero for tasks that don't retain memory between measurements
- ⚠️ `memory_peak_mb`: Captures significant peaks, but may be zero for small/fast tasks

**When memory metrics are useful:**

- Long-running tasks that accumulate data
- Detecting memory leaks
- Tasks that explicitly retain large objects
- Comparing relative memory usage between different implementations

**Example of measurable memory usage:**

```php
$promise = async(function() {
    // This will show memory usage because data is retained until return
    $largeArray = array_fill(0, 1000000, 'data');
    sleep(1); // Keep data in memory
    return count($largeArray);
}, enableBenchmark: true);
```

### `ParalliteClient` (Advanced)

For advanced use cases requiring manual control.

#### Constructor

```php
public function __construct(
    string $socketPath = '',
    bool $autoManageDaemon = false,
    ?string $projectRoot = null
)
```

#### Key Methods

##### `promise(Closure $closure): Promise`

Create a Promise with chaining support.

##### `async(Closure $closure): array`

Lower-level API returning raw futures.

##### `await(Promise|array $future): mixed`

Await a Promise or future.

##### `awaitAll(array $closures): array`

Batch operation to await multiple closures.

```php
$results = $client->awaitAll([
    fn() => task1(),
    fn() => task2(),
]);
```

##### `awaitMultiple(array $promises): array`

Await multiple promises/futures in parallel. Non-promise values pass through unchanged.

```php
$results = $client->awaitMultiple([
    'static' => 'value',
    'promise1' => $client->promise(fn() => task1()),
    'promise2' => $client->promise(fn() => task2()),
]);
// ['static' => 'value', 'promise1' => result1, 'promise2' => result2]
```

## 🔧 Advanced Usage

### Custom Socket Path

```php
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

## 🔧 Advanced: Using ParalliteClient Directly

For advanced use cases where you need manual daemon control or custom socket paths, you can use `ParalliteClient`
directly:

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

## 🌐 Platform Support

| Platform    | Status            | Notes                |
|-------------|-------------------|----------------------|
| **Linux**   | ✅ Fully Supported | x86_64, ARM64        |
| **macOS**   | ✅ Fully Supported | Intel, Apple Silicon |
| **Windows** | ✅ Fully Supported | x86_64, ARM64        |

## 📊 Performance

Parallite provides significant speedup for I/O-bound and CPU-bound tasks:

| Tasks   | Sequential | Parallel | Speedup   |
|---------|------------|----------|-----------|
| 3 × 1s  | 3.0s       | ~1.0s    | **3.0x**  |
| 5 × 2s  | 10.0s      | ~2.0s    | **5.0x**  |
| 10 × 1s | 10.0s      | ~1.0s    | **10.0x** |

### 🌟 Real-World Example

Want to see Parallite in action with real data? Check out our comprehensive test that demonstrates:

- **Parallel API fetching** from multiple endpoints (users, posts, comments, albums, photos)
- **Complex data processing** with analytics and statistics
- **File operations** with automatic cleanup
- **Chunked processing** for large datasets
- **Performance benchmarking** with detailed metrics

```bash
# Run the real-world data processing test
RUN_REAL_WORLD_TESTS=1 vendor/bin/pest tests/Feature/RealWorldDataProcessingTest.php --no-coverage
```

This test showcases:

- ✅ Fetching and processing data from JSONPlaceholder API
- ✅ Parallel processing of 100+ photos with metadata extraction
- ✅ Chunked processing of 200 todos with user analytics
- ✅ Automatic file cleanup after processing
- ✅ Detailed performance metrics and statistics

**Example output:**

```
🌐 Real World Data Processing Test
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📥 Fetching multiple datasets in parallel...
✅ Fetched datasets:
   • Todos: 200
   • Albums: 100
   • Photos: 100

🔄 Processing photos and saving metadata to files...
✅ Processed 100 photos and saved to files

📊 AGGREGATED RESULTS
   • Total Todos: 200
   • Completed: 100 (50.00%)
   • Total Photos Processed: 100
   • Albums with Photos: 10

⚡ Performance Metrics:
   • Total Processing Time: 2.45s
   • Items per Second: 81.63
```

Perfect for learning how to build real-world parallel processing applications! 🚀

## 🐛 Troubleshooting

### Binary Not Found

```bash
composer parallite:install
```

### Daemon Connection Failed

```bash
# Check if daemon is running
ps aux | grep parallite

# Check socket file
ls -la /tmp/parallite.sock

# Start daemon manually
./vendor/bin/parallite --socket=/tmp/parallite.sock
```

### Permission Denied

```bash
# Make binary executable
chmod +x vendor/bin/parallite
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Parallite Daemon**: [b7s/parallite](https://github.com/b7s/parallite)
- **Closure Serialization**: [opis/closure](https://github.com/opis/closure)
- **Socket Communication**: [php-socket](https://www.php.net/manual/en/booksockets.php)
- **Composer**: [composer](https://getcomposer.org/)
- **PHP**: [php](https://www.php.net/)
- **Inspired by**: [Pokio](https://github.com/nunomaduro/pokio)

## 📮 Support

- **Issues**: [GitHub Issues](https://github.com/parallite/parallite-php/issues)
- **Discussions**: [GitHub Discussions](https://github.com/parallite/parallite-php/discussions)

---

Made with ❤️ by the Parallite community
