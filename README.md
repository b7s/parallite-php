<div align="center">

<img src="docs/art/parallite-logo.webp" alt="Parallite Logo" width="128">

# Parallite {PHP Client}

[![Latest Version](https://img.shields.io/packagist/v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![PHP Version](https://img.shields.io/packagist/php-v/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)
[![License](https://img.shields.io/packagist/l/parallite/parallite-php.svg?style=flat-square)](https://packagist.org/packages/parallite/parallite-php)

**Execute PHP closures in true parallel** - A standalone PHP client for [Parallite](https://github.com/b7s/parallite),
enabling real parallel execution of PHP code without the limitations of traditional PHP concurrency.

</div>

---

## ✨ Features

- 🚀 **True Parallel Execution** - Execute multiple PHP closures simultaneously
- 🎯 **Simple async/await API** - Familiar Promise-like interface
- 🔄 **Promise Chaining** - Chainable `then()`, `catch()`, and `finally()` methods
- 🌍 **Cross-platform** - Works on Windows, Linux and macOS
- ⏯️️ **Automatic Daemon Management** - Optional auto-start/stop of Parallite daemon
- ⚡ **Binary MessagePack Transport** - Ultra-fast daemon communication (2-5x faster than JSON)

## 📋 Requirements

- PHP 8.3+
- ext-sockets
- ext-pcntl
- ext-zip
- rybakit/msgpack
- opis/closure
- Composer 2+

## 📦 Installation

```bash
composer require parallite/parallite-php
```

Add the install/update scripts to your `composer.json`:

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

> See more about these scripts: [Installation Guide](docs/installation.md).

After adding the scripts, run (to download Parallite binary):

```bash
composer update
```

## 🚀 Quick Start

```php
<?php

require 'vendor/autoload.php';

// Basic usage - no imports needed!
$result = await(async(fn() => 'Hello World'));
echo $result; // Hello World

// Parallel execution
$p1 = async(fn() => sleep(1) && 'Task 1');
$p2 = async(fn() => sleep(1) && 'Task 2');
$p3 = async(fn() => sleep(1) && 'Task 3');

$results = await([$p1, $p2, $p3]);
// Total time: ~1s (parallel) instead of 3s (sequential)

// Promise chaining
$result = await(
    async(fn() => 1 + 2)
        ->then(fn($n) => $n * 2)
        ->then(fn($n) => $n + 5)
);
echo $result; // 11

// Error handling
$result = await(
    async(function () {
        throw new Exception('Oops!');
    })->catch(fn($e) => 'Caught: ' . $e->getMessage())
);
echo $result; // Caught: Task failed: Oops!
```

**That's it!** The daemon is automatically managed - no manual setup required!

## 📚 Documentation

- **[Quick Start Guide](docs/quick-start.md)** - Get up and running in minutes
- **[Installation](docs/installation.md)** - Detailed installation instructions
- **[Configuration](docs/configuration.md)** - Customize daemon behavior and PHP includes
- **[API Reference](docs/api-reference.md)** - Complete API documentation
- **[Complex Data Handling](docs/complex-data-handling.md)** - How to handle complex data structures
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues and solutions
- **[Examples](examples/)** - Real-world usage examples

## ⚡ Performance

Parallite provides significant speedup for I/O-bound and CPU-bound tasks:

| Tasks   | Sequential | Parallel | Speedup   |
|---------|------------|----------|-----------|
| 3 × 1s  | 3.0s       | ~1.0s    | **3.0x**  |
| 5 × 2s  | 10.0s      | ~2.0s    | **5.0x**  |
| 10 × 1s | 10.0s      | ~1.0s    | **10.0x** |

```
Parallelism is beneficial when:

✅ CPU-intensive operations (image processing, complex calculations)
✅ Independent I/O-bound operations (external API calls, multiple databases)
✅ Database with good concurrency (MongoDB, PostgreSQL, SQL Server, MySQL)
❌ SQLite with concurrent writes
❌ Operations are already very fast
```

### Real-World Example

```php
// Fetch multiple APIs in parallel
$promises = [
    'users'    => async(fn() => file_get_contents('https://api.example.com/users')),
    'posts'    => async(fn() => file_get_contents('https://api.example.com/posts')),
    'comments' => async(fn() => file_get_contents('https://api.example.com/comments')),
];

$data = await($promises);
// 3x faster than sequential fetching!
```

Run the real-world test suite (uses data available at https://jsonplaceholder.typicode.com):

```bash
RUN_REAL_WORLD_TESTS=1 vendor/bin/pest tests/Feature/RealWorldDataProcessingTest.php --no-coverage
```

## 🌐 Platform Support

| Platform    | Status            | Notes                |
|-------------|-------------------|----------------------|
| **Linux**   | ✅ Fully Supported | x86_64, ARM64        |
| **macOS**   | ✅ Fully Supported | Intel, Apple Silicon |
| **Windows** | ✅ Fully Supported | x86_64, ARM64        |

## 🐛 Troubleshooting

Having issues? Check the **[Troubleshooting Guide](docs/troubleshooting.md)** for solutions.

**Quick tip:** Never capture `$this` in closures passed to `async()`. Use static methods or extract primitives instead.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Parallite Daemon**: [b7s/parallite](https://github.com/b7s/parallite)
- **Closure Serialization**: [opis/closure](https://github.com/opis/closure)
- **MessagePack**: [rybakit/msgpack](https://github.com/rybakit/msgpack)
- **Inspired by**: [Pokio](https://github.com/nunomaduro/pokio)

## 📮 Support

- **Issues**: [GitHub Issues](https://github.com/parallite/parallite-php/issues)
- **Discussions**: [GitHub Discussions](https://github.com/parallite/parallite-php/discussions)

---

<div align="center">

Made with ❤️ by the Parallite community

</div>
