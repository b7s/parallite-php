<?php

declare(strict_types=1);

/**
 * Basic Parallite Example - TRUE PARALLEL EXECUTION
 *
 * This example demonstrates how to use the async() and await() helper functions
 * to execute PHP closures in parallel - no setup required!
 */

require __DIR__.'/../vendor/autoload.php';

// No imports needed - async() and await() are available globally!

echo "=== Parallite - Basic Examples ===\n\n";

try {
    // Example 1: Basic async/await
    echo "1. Basic async/await\n";
    echo "--------------------\n";
    $start = microtime(true);

    $promise1 = async(function () {
        sleep(1);

        return 'Task 1 completed (1s)';
    });

    $promise2 = async(function () {
        sleep(2);

        return 'Task 2 completed (2s)';
    });

    $promise3 = async(function () {
        sleep(3);

        return 'Task 3 completed (3s)';
    });

    echo "   ✓ All tasks submitted (executing in parallel)\n";

    $result1 = await($promise1);
    $result2 = await($promise2);
    $result3 = await($promise3);

    $duration = round(microtime(true) - $start, 2);

    echo "   Results:\n";
    echo "     - {$result1}\n";
    echo "     - {$result2}\n";
    echo "     - {$result3}\n";
    echo "   Total time: {$duration}s\n";

    if ($duration < 4) {
        echo "   ✓ PARALLEL execution confirmed! (would be 6s if sequential)\n";
        echo '   Speedup: '.round(6 / $duration, 2)."x\n";
    }

    echo "\n";

    // Example 2: Promise chaining
    echo "2. Promise chaining with then()\n";
    echo "--------------------------------\n";
    $start = microtime(true);

    $result = await(
        async(fn () => 10)
            ->then(fn ($n) => $n * 2)
            ->then(fn ($n) => $n + 5)
    );

    echo "   Result: {$result} (10 * 2 + 5)\n\n";

    // Example 3: Working with data
    echo "3. Working with data\n";
    echo "--------------------\n";

    $numbers = [1, 2, 3, 4, 5];
    $promises = [];

    foreach ($numbers as $n) {
        $promises[] = async(function () use ($n) {
            usleep(100000); // 100ms

            return $n * $n;
        });
    }

    $squares = [];
    foreach ($promises as $promise) {
        $squares[] = await($promise);
    }

    echo '   Numbers: '.json_encode($numbers)."\n";
    echo '   Squares: '.json_encode($squares)."\n\n";

    // Example 4: Error handling with catch()
    echo "4. Error handling with catch()\n";
    echo "-------------------------------\n";

    $result = await(
        async(function () {
            throw new RuntimeException('Simulated error');
        })->catch(fn ($e) => 'Rescued: '.$e->getMessage())
    );

    echo "   Result: {$result}\n\n";

    echo "=== All examples completed successfully! ===\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
