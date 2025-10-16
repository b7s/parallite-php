<div align="center">

<img src="art/parallite-logo.webp" alt="Parallite Logo" width="128">
<h1>Parallite {PHP Client}</h1>

</div>

[![Latest Version](https://img.shields.io/packagist/v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![PHP Version](https://img.shields.io/packagist/php-v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![License](https://img.shields.io/packagist/l/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)

**Execute PHP closures in true parallel** - A standalone PHP client for [Parallite](https://github.com/b7s/parallite), enabling real parallel execution of PHP code without the limitations of traditional PHP concurrency.

## ✨ Features

- 🚀 **True Parallel Execution** - Execute multiple PHP closures simultaneously
- 🔄 **Simple async/await API** - Familiar Promise-like interface
- 🌍 **Cross-platform** - Works on Windows, Linux and macOS
- ⚡ **Zero Configuration** - Works out of the box
- 🎯 **Automatic Daemon Management** - Optional auto-start/stop of Parallite daemon
- 📦 **Standalone** - No framework dependencies required
- 🔧 **Configurable** - Fine-tune worker pools, timeouts, and more
- 📝 **Configuration** - Load Daemon configuration and PHP required files from parallite.json (optional)
- 📚 **Documentation** - Comprehensive API reference and examples

## 📋 Requirements

- PHP 8.2 or higher
- ext-sockets
- Composer 2+

## 📦 Installation

Install via Composer:

```bash
composer require parallite/parallite-php
```

The Parallite binary will be automatically downloaded and installed during `composer install`.

### Manual Binary Installation

If needed, you can manually install or update the binary:

```bash
# Install binary
composer parallite:install

# Update to latest version
composer parallite:update

# Force reinstall
composer parallite:install -- --force
```

## 🚀 Quick Start

### Option 1: Manual Daemon Management

Start the daemon manually and connect to it:

**Linux/macOS:**
```bash
# Terminal 1: Start daemon
./vendor/bin/parallite --socket=/tmp/parallite.sock
```

**Windows:**
```cmd
# Terminal 1: Start daemon
vendor\bin\parallite.exe --socket=\\.\pipe\parallite
```

```php
<?php

require 'vendor/autoload.php';

use Parallite\ParalliteClient;

// Unix/Linux/macOS
$client = new ParalliteClient('/tmp/parallite.sock');

// Windows
$client = new ParalliteClient('\\\\.\\pipe\\parallite');

// Or use default (auto-detects platform)
$client = new ParalliteClient(); 

// Submit tasks
$future1 = $client->async(fn() => sleep(1) && 'Task 1');
$future2 = $client->async(fn() => sleep(2) && 'Task 2');
$future3 = $client->async(fn() => sleep(3) && 'Task 3');

// Await results
echo $client->await($future1) . "\n"; // Task 1
echo $client->await($future2) . "\n"; // Task 2
echo $client->await($future3) . "\n"; // Task 3

// Total time: ~3s (parallel) instead of 6s (sequential)
```

### Option 2: Automatic Daemon Management

Let the client manage the daemon lifecycle:

```php
<?php

require 'vendor/autoload.php';

use Parallite\ParalliteClient;

// Client automatically starts and stops the daemon
$client = new ParalliteClient(
    '/tmp/parallite.sock',
    autoManageDaemon: true
);

$future = $client->async(fn() => 'Hello from parallel task!');
$result = $client->await($future);

echo $result; // Hello from parallel task!

// Daemon is automatically stopped when script ends
```

## 📚 Usage Examples

### Basic Parallel Execution

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient('/tmp/parallite.sock');

// Submit multiple tasks
$futures = [
    $client->async(fn() => heavyComputation1()),
    $client->async(fn() => heavyComputation2()),
    $client->async(fn() => heavyComputation3()),
];

// Collect results
$results = array_map(
    fn($future) => $client->await($future),
    $futures
);
```

### Using `awaitAll()` Convenience Method

```php
$client = new ParalliteClient('/tmp/parallite.sock');

$results = $client->awaitAll([
    fn() => fetchUser(1),
    fn() => fetchPosts(1),
    fn() => fetchComments(1),
]);

[$user, $posts, $comments] = $results;
```

### Working with Data

```php
$client = new ParalliteClient('/tmp/parallite.sock');

$numbers = range(1, 10);
$futures = [];

foreach ($numbers as $n) {
    $futures[] = $client->async(fn() => $n * $n);
}

$squares = [];
foreach ($futures as $future) {
    $squares[] = $client->await($future);
}

print_r($squares); // [1, 4, 9, 16, 25, 36, 49, 64, 81, 100]
```

### Error Handling

```php
$client = new ParalliteClient('/tmp/parallite.sock');

try {
    $future = $client->async(function () {
        throw new RuntimeException('Something went wrong!');
    });
    
    $client->await($future);
} catch (RuntimeException $e) {
    echo "Error: {$e->getMessage()}";
}
```

### Real-World Example: Parallel API Calls

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient('/tmp/parallite.sock', autoManageDaemon: true);

$start = microtime(true);

// Fetch data from multiple APIs in parallel
$results = $client->awaitAll([
    fn() => file_get_contents('https://api.example.com/users'),
    fn() => file_get_contents('https://api.example.com/posts'),
    fn() => file_get_contents('https://api.example.com/comments'),
]);

$duration = microtime(true) - $start;

echo "Fetched 3 APIs in {$duration}s (would be 3x slower if sequential)\n";
```

## 🔌 Socket Paths

Parallite uses different socket types depending on the platform:

| Platform | Socket Type | Example Path |
|----------|-------------|--------------|
| **Linux** | Unix Socket | `/tmp/parallite.sock` |
| **macOS** | Unix Socket | `/tmp/parallite.sock` |
| **Windows** | Named Pipe | `\\\\.\\pipe\\parallite` |

### Platform-Specific Examples

**Linux/macOS:**
```php
$client = new ParalliteClient('/tmp/parallite.sock');
```

**Windows:**
```php
$client = new ParalliteClient('\\\\.\\pipe\\parallite');
```

**Auto-detect (recommended):**
```php
// Uses default path for current platform
$client = new ParalliteClient();
```

## ⚙️ Configuration

Create a `parallite.json` file in your project root to configure Parallite:

```json
{
  "php_includes": [
    "config/bootstrap.php",
    "vendor/autoload.php"
  ],
  "go_overrides": {
    "timeout_ms": 30000,
    "fixed_workers": 3,
    "prefix_name": "my_worker",
    "fail_mode": "continue"
  }
}
```

### Configuration Options

#### `php_includes`
Array of PHP files to load in worker processes. Useful for loading configuration, helpers, or dependencies.

#### `go_overrides`
Daemon configuration options:

- **`timeout_ms`** (int): Task timeout in milliseconds (default: 30000)
- **`fixed_workers`** (int): Number of worker processes (default: 0 = auto)
- **`prefix_name`** (string): Prefix for worker process names (default: "parallite_worker")
- **`fail_mode`** (string): How to handle failures: "continue" or "stop" (default: "continue")

## 🎯 API Reference

### `ParalliteClient`

#### Constructor

```php
public function __construct(
    string $socketPath = '',
    bool $autoManageDaemon = false,
    ?string $projectRoot = null
)
```

**Parameters:**
- `$socketPath`: Socket path for daemon communication
  - **Empty string** (default): Auto-detects platform and uses default path
  - **Unix/Linux/macOS**: `/tmp/parallite.sock`
  - **Windows**: `\\\\.\\pipe\\parallite`
- `$autoManageDaemon`: If true, automatically starts/stops daemon
- `$projectRoot`: Project root directory (auto-detected if null)

#### Methods

##### `async(Closure $closure): array`

Submit a task for parallel execution.

**Returns:** Future array with `['socket' => resource, 'task_id' => string]`

```php
$future = $client->async(fn() => heavyTask());
```

##### `await(?array $future): mixed`

Await the result of a previously submitted task.

**Returns:** The result of the task execution

```php
$result = $client->await($future);
```

##### `awaitAll(array $closures): array`

Submit multiple tasks and await all results.

**Returns:** Array of results in the same order

```php
$results = $client->awaitAll([
    fn() => task1(),
    fn() => task2(),
    fn() => task3(),
]);
```

##### `stopDaemon(): void`

Manually stop the daemon (only if `autoManageDaemon` is true).

```php
$client->stopDaemon();
```

##### `static getDefaultSocketPath(): string`

Get the default socket path for the current platform.

**Returns:** Platform-specific socket path

```php
$socketPath = ParalliteClient::getDefaultSocketPath();
// Linux/macOS: /tmp/parallite_{pid}.sock
// Windows: \\.\pipe\parallite_{pid}
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

## 🌐 Platform Support

| Platform | Status | Notes |
|----------|--------|-------|
| **Linux** | ✅ Fully Supported | x86_64, ARM64 |
| **macOS** | ✅ Fully Supported | Intel, Apple Silicon |
| **Windows** | ✅ Fully Supported | x86_64, ARM64 |

## 📊 Performance

Parallite provides significant speedup for I/O-bound and CPU-bound tasks:

| Tasks | Sequential | Parallel | Speedup |
|-------|-----------|----------|---------|
| 3 × 1s | 3.0s | ~1.0s | **3.0x** |
| 5 × 2s | 10.0s | ~2.0s | **5.0x** |
| 10 × 1s | 10.0s | ~1.0s | **10.0x** |

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
