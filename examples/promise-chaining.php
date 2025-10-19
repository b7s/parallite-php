<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use function Parallite\async;
use function Parallite\await;

echo "=== Promise Chaining Examples ===\n\n";

// Example 1: Basic then chaining
echo "1. Basic then() chaining:\n";
$promise = async(fn (): int => 1 + 2)
    ->then(fn ($result): int => $result + 2)
    ->then(fn ($result): int => $result * 2);

$result = await($promise);
echo "Result: {$result}\n"; // int(10)
echo "Expected: 10\n\n";

// Example 2: Error handling with catch
echo "2. Error handling with catch():\n";
$promise = async(function () {
    throw new Exception('Error');
})->catch(function (Throwable $e) {
    return 'Rescued: ' . $e->getMessage();
});

$result = await($promise);
echo "Result: {$result}\n"; // string(16) "Rescued: Error"
echo "Expected: Rescued: Error\n\n";

// Example 3: Chaining then after catch
echo "3. Chaining then() after catch():\n";
$promise = async(function () {
    throw new Exception('Failure');
})
    ->catch(fn (Throwable $e) => 'recovered')
    ->then(fn ($result) => strtoupper($result));

$result = await($promise);
echo "Result: {$result}\n"; // string(9) "RECOVERED"
echo "Expected: RECOVERED\n\n";

// Example 4: Using finally
echo "4. Using finally():\n";
$finallyCalled = false;

$promise = async(fn () => 'success')
    ->then(fn ($r) => strtoupper($r))
    ->finally(function () use (&$finallyCalled) {
        $finallyCalled = true;
        echo "Finally block executed!\n";
    });

$result = await($promise);
echo "Result: {$result}\n"; // string(7) "SUCCESS"
echo "Finally called: " . ($finallyCalled ? 'yes' : 'no') . "\n\n";

// Example 5: Parallel execution with promises
echo "5. Parallel execution with promises:\n";
$start = microtime(true);

$promise1 = async(function () {
    sleep(1);
    return 'Task 1';
})->then(fn ($r) => $r . ' completed');

$promise2 = async(function () {
    sleep(1);
    return 'Task 2';
})->then(fn ($r) => $r . ' completed');

$promise3 = async(function () {
    sleep(1);
    return 'Task 3';
})->then(fn ($r) => $r . ' completed');

$result1 = await($promise1);
$result2 = await($promise2);
$result3 = await($promise3);

$duration = microtime(true) - $start;

echo "Result 1: {$result1}\n";
echo "Result 2: {$result2}\n";
echo "Result 3: {$result3}\n";
echo "Duration: " . round($duration, 2) . "s (should be ~1s, not 3s)\n\n";

// Example 6: Complex data transformation
echo "6. Complex data transformation:\n";
$promise = async(fn () => ['name' => 'john', 'age' => 30])
    ->then(fn ($data) => array_merge($data, ['name' => strtoupper($data['name'])]))
    ->then(fn ($data) => array_merge($data, ['age' => $data['age'] + 1]))
    ->then(fn ($data) => json_encode($data));

$result = await($promise);
echo "Result: {$result}\n";
echo "Expected: {\"name\":\"JOHN\",\"age\":31}\n\n";

// Example 7: Invoking promise directly
echo "7. Invoking promise directly:\n";
$promise = async(fn () => 42)
    ->then(fn ($n) => $n * 2);

$result = $promise(); // Direct invocation
echo "Result: {$result}\n";
echo "Expected: 84\n\n";

echo "=== All examples completed! ===\n";
