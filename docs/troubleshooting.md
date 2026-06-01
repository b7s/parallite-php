# Troubleshooting

Common issues and solutions when working with Parallite. Use `pd()` inside `async()` calls — it throws an exception with the dump data.

## Fork Not Available

**Problem:** Tasks run sequentially instead of in parallel.

**Diagnosis:**

```bash
php -m | grep -E 'pcntl|posix'
```

**Solution:**

Install the required extensions:

```bash
# Ubuntu/Debian
sudo apt-get install php-pcntl php-posix

# macOS (Homebrew PHP — usually included by default)
# No action needed
```

Check with `ParalliteClient::isForkMode()`:

```php
use Parallite\ParalliteClient;

$client = new ParalliteClient();
echo $client->isForkMode() ? 'Fork mode' : 'Sequential mode';
```

## Capturing `$this` in Closures

**Problem:** Forked process copies the entire parent object when `$this` is captured.

**Impact:** Large memory usage in child processes, potentially hitting `memory_limit`.

**Solution:**

Extract primitive values before the closure:

```php
// Bad — captures $this (copies entire object)
$promises = [
    'customers' => async(fn () => $this->getCustomerStatistics()),
];

// Good — unbound closure
$promises = [
    'customers' => async(function () {
        return [
            'total' => Customer::query()->count(),
            'with_orders' => Customer::query()->has('orders')->count(),
        ];
    }),
];

// Good — explicitly inject what you need
$service = $this->service;
async(function () use ($service) {
    return $service->doSomething();
});
```

### Key Rules

- **Never capture `$this`** directly in closures passed to `async()`
- **Prefer static/service methods** when shared state is required
- **Only inject primitives** (scalars, arrays) via `use`

## Memory Issues

**Problem:** Forked processes running out of memory.

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
- Increase PHP memory limit if needed
- Avoid capturing `$this` (see above)

## Child Process Crashes

**Problem:** Child processes die unexpectedly.

**Common causes:**

1. **Fatal PHP errors** in task code
2. **Segmentation faults** from extensions
3. **Out of memory**

**Solution:**

Add error handling inside the closure:

```php
$promise = async(function () {
    try {
        return riskyOperation();
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
});
```

## Temp File Issues

**Problem:** Error reading fork result file.

**Cause:** The temp directory may not be writable, or disk is full.

**Solution:**

```bash
# Check temp directory is writable
php -r "echo sys_get_temp_dir() . PHP_EOL;"
ls -la /tmp

# Check disk space
df -h /tmp
```

## Laravel Integration

**Problem:** Laravel application not available in forked processes.

**Solution:**

Create `bootstrap/parallite.php`:

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

**Important:** Do not use the HTTP kernel. The HTTP request lifecycle termination will cause issues in forked processes.

## Still Having Issues?

- **Check examples:** Review working examples in the `examples/` directory
- **Run tests:** Execute the test suite to verify your setup
- **GitHub Issues:** [Report a bug](https://github.com/parallite/parallite-php/issues)
- **Discussions:** [Ask for help](https://github.com/parallite/parallite-php/discussions)
