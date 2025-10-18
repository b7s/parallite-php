# Troubleshooting

Common issues and solutions when working with Parallite.

## Binary Not Found

**Problem:** The `parallite` binary is not available after installation.

**Solution:**

```bash
php vendor/parallite/parallite-php/bin/parallite-install
```

Or add automatic installation to your `composer.json`:

```json
{
  "scripts": {
    "post-install-cmd": [
      "@php vendor/parallite/parallite-php/bin/parallite-install"
    ]
  }
}
```

See more at [Installation Guide](installation.md).

## Daemon Connection Failed

**Problem:** Cannot connect to the Parallite daemon.

**Diagnosis:**

```bash
# Check if daemon is running
ps aux | grep parallite

# Check socket file exists
ls -la /tmp/parallite.sock
```

**Solution:**

Start the daemon manually (the binary is auto-resolved from the latest installed version):

```bash
# The binary path is managed automatically
# It's located at vendor/parallite/parallite-php/bin/parallite-bin/parallite-{version}.bin
./vendor/parallite/parallite-php/bin/parallite-bin/parallite-{version}.bin --socket=/tmp/parallite.sock
```

Or use automatic daemon management:

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient(autoManageDaemon: true);
```

## Permission Denied

**Problem:** Cannot execute the Parallite binary.

**Solution:**

The installer automatically sets the correct permissions. If you still have issues, the binary is located at:

```bash
# Find and make executable (Unix/Linux/macOS)
chmod +x vendor/parallite/parallite-php/bin/parallite-bin/parallite-*.bin
```

## Serialization Failures

### Problem

Closures fail to serialize with error:

```
Opis\Closure\ReflectionClosure::getCallableForm(): Return value must be of type ?callable, array returned
```

### Cause

Passing closures that capture `$this` forces `opis/closure` to serialize the entire object instance, including
non-serializable dependencies (models, request objects, container instances).

### Impact

Workers cannot deserialize the closure payload, causing all parallel tasks using those closures to fail before
execution.

### Solution

**Option 1: Detach context**

Extract primitive values or move logic into static helpers:

```php
// ❌ Bad - captures $this
$promises = [
    'customers' => async(fn () => $this->getCustomerStatistics()),
];

// ✅ Good - unbound closure
$promises = [
    'customers' => async(function () {
        return [
            'total' => Customer::query()->count(),
            'with_orders' => Customer::query()->has('orders')->count(),
            'without_orders' => Customer::query()->doesntHave('orders')->count(),
        ];
    }),
];
```

**Option 2: Use static methods**

```php
// ✅ Good - static method
$promises = [
    'customers' => async(fn () => self::getCustomerStatistics()),
];
```

### Key Rules

- **Never capture `$this`** directly in closures passed to `async()`
- **Prefer static/service methods** when shared state is required
- **Only serialize primitives** (scalars, arrays)
- **Laravel users:** Convert Eloquent results with `->toArray()` or `->all()`

## Timeout Errors

**Problem:** Tasks are timing out before completion.

**Solution:**

Increase the timeout in `parallite.json`:

```json
{
  "go_overrides": {
    "timeout_ms": 900000
  }
}
```

Default is 15 minutes (900000ms). Adjust based on your workload.

## Memory Issues

**Problem:** Workers running out of memory.

**Diagnosis:**

Enable benchmark mode to track memory usage:

```php
$promise = async(fn () => heavyTask(), enableBenchmark: true);
$result = await($promise);

$benchmark = $promise->getBenchmark();
echo "Memory peak: {$benchmark->memoryPeakMb}MB\n";
```

**Solution:**

- Break large tasks into smaller chunks
- Process data in batches
- Use generators for large datasets
- Increase PHP memory limit if needed

## Worker Process Crashes

**Problem:** Worker processes die unexpectedly.

**Common causes:**

1. **Fatal PHP errors** in task code
2. **Segmentation faults** from extensions
3. **Out of memory**

**Solution:**

Check worker logs and add error handling:

```php
$promise = async(function () {
    try {
        return riskyOperation();
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
});
```

## Laravel Integration Issues

**Problem:** Laravel application not available in workers.

**Solution:**

Create `bootstrap/parallite.php`:

- Loads the Composer autoloader
- Bootstraps the Laravel application using the Console Kernel
- Makes the Laravel application available without executing HTTP lifecycle

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
```

Then add to `parallite.json`:

```json
{
  "php_includes": [
    "bootstrap/parallite.php"
  ]
}
```

**Important:** Do not use the HTTP kernel. This caused the PHP worker process
to die prematurely before sending responses back to the Go daemon.

_(Maybe other frameworks have a similar solution)_

## Extension Not Loaded

**Problem:** Missing required PHP extensions.

**Solution:**

Install required extensions:

```bash
# Ubuntu/Debian
sudo apt-get install php-msgpack php-sockets php-zip

# macOS (Homebrew)
pecl install msgpack

# Verify installation
php -m | grep -E 'msgpack|sockets|zip'
```

## Still Having Issues?

- **Check examples:** Review working examples in the `examples/` directory
- **Run tests:** Execute the test suite to verify your setup
- **GitHub Issues:** [Report a bug](https://github.com/parallite/parallite-php/issues)
- **Discussions:** [Ask for help](https://github.com/parallite/parallite-php/discussions)
