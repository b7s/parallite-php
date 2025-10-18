# Quick Start

Parallite exposes global helpers so you can start executing closures in parallel immediately. After requiring Composer's autoloader, use `async()` and `await()`—no extra bootstrapping needed.

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

$result = await(async(fn () => 'Hello World'));
echo $result; // Hello World
```

## Promise Chaining

```php
$result = await(
    async(fn () => 1 + 2)
        ->then(fn ($n) => $n * 2)
        ->then(fn ($n) => $n + 5)
);

echo $result; // 11
```

## Error Handling

```php
$result = await(
    async(function () {
        throw new Exception('Oops!');
    })->catch(fn ($e) => 'Caught: ' . $e->getMessage())
);

echo $result; // Caught: Oops!
```

## Parallel Execution

```php
$p1 = async(fn () => sleep(1) && 'Task 1');
$p2 = async(fn () => sleep(1) && 'Task 2');
$p3 = async(fn () => sleep(1) && 'Task 3');

await([$p1, $p2, $p3]);
// ~1s total (instead of 3s sequential)
```

## Working with Data

```php
$numbers = range(1, 10);
$promises = [];

foreach ($numbers as $n) {
    $promises[] = async(fn () => $n * $n);
}

$squares = await($promises);
print_r($squares); // [1, 4, 9, 16, ...]
```

## Real-World Example

```php
$promises = [
    'users' => async(fn () => file_get_contents('https://api.example.com/users')),
    'posts' => async(fn () => file_get_contents('https://api.example.com/posts')),
    'comments' => async(fn () => file_get_contents('https://api.example.com/comments')),
];

$data = await($promises);
```

See `examples/` for end-to-end scripts that exercise the client in real scenarios.
