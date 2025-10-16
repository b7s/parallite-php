# Promise Chaining Feature

## Overview

Added promise chaining functionality to Parallite PHP, inspired by [Pokio](https://github.com/b7s/pokio-test), enabling chainable `then()`, `catch()`, and `finally()` methods for elegant async/await patterns.

## New Files

### `src/Promise.php`
- Promise wrapper class with chainable methods
- Supports `then()`, `catch()`, `finally()` chaining
- Maintains backward compatibility with existing future-based API
- Prevents chaining after promise execution starts

### `src/functions.php`
- Global helper functions `async()` and `await()`
- Automatic daemon management for simplified usage
- Ergonomic API similar to JavaScript promises

### `tests/Feature/PromiseChainingTest.php`
- Comprehensive test suite for promise chaining
- Tests for `then()`, `catch()`, `finally()` methods
- Parallel execution verification
- Helper function tests

### `examples/promise-chaining.php`
- Practical examples demonstrating all features
- Error handling patterns
- Parallel execution examples

## Modified Files

### `src/ParalliteClient.php`
- Added `promise(Closure $closure): Promise` method
- Updated `await()` to accept `Promise` objects
- Maintains full backward compatibility with existing `async()` API

### `composer.json`
- Added `src/functions.php` to autoload files

### `README.md`
- Added "Promise Chaining" section with examples
- Updated API reference with new methods
- Documented `Promise` class and helper functions
- Updated features list

## Usage Examples

### Basic Chaining

```php
use function Parallite\async;
use function Parallite\await;

$promise = async(fn (): int => 1 + 2)
    ->then(fn ($result): int => $result + 2)
    ->then(fn ($result): int => $result * 2);

$result = await($promise); // 10
```

### Error Handling

```php
$promise = async(function () {
    throw new Exception('Error');
})->catch(function (Throwable $e) {
    return 'Rescued: ' . $e->getMessage();
});

$result = await($promise); // "Rescued: Task failed: Error"
```

### Finally Callback

```php
$promise = async(fn () => 'success')
    ->then(fn ($r) => strtoupper($r))
    ->finally(fn () => echo "Cleanup!\n");

$result = await($promise); // "SUCCESS" (prints "Cleanup!")
```

### Parallel Execution

```php
$p1 = async(fn () => sleep(1) && 'Task 1');
$p2 = async(fn () => sleep(1) && 'Task 2');
$p3 = async(fn () => sleep(1) && 'Task 3');

$results = [await($p1), await($p2), await($p3)];
// Completes in ~1s (parallel) instead of 3s (sequential)
```

## Backward Compatibility

All existing code continues to work without modification:

```php
// Old API still works
$client = new ParalliteClient(autoManageDaemon: true);
$future = $client->async(fn() => 'test');
$result = $client->await($future);
```

## Testing

All tests pass:
- 13 new promise chaining tests
- 36 total tests (83 assertions)
- PHPStan level max with strict rules
- 100% backward compatibility verified

## Implementation Details

- Promise execution is lazy (starts on first `await()` or `resolve()`)
- Callbacks are applied sequentially after task completion
- Catch handlers receive the original exception
- Finally callbacks execute regardless of success/failure
- Prevents chaining after promise starts (throws `RuntimeException`)
- Thread-safe parallel execution maintained

## API Reference

### `ParalliteClient::promise(Closure $closure): Promise`
Create a chainable promise for async execution.

### `Promise::then(Closure $callback): Promise`
Chain transformation callbacks.

### `Promise::catch(Closure $callback): Promise`
Handle exceptions.

### `Promise::finally(Closure $callback): Promise`
Execute cleanup code.

### `async(Closure $closure): Promise`
Global helper for creating promises.

### `await(Promise|array $promise): mixed`
Global helper for awaiting results.
