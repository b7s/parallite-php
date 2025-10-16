<?php

declare(strict_types=1);

/**
 * Basic Parallite Example - TRUE PARALLEL EXECUTION
 * 
 * This example demonstrates how to use the ParalliteClient class
 * to execute PHP closures in parallel.
 * 
 * FIRST: Start daemon: ./vendor/bin/parallite --socket=/tmp/parallite-custom.sock
 * SECOND: Run example: php examples/basic.php
 * 
 * Or use autoManageDaemon: true to start daemon automatically
 */

require __DIR__.'/../vendor/autoload.php';

use Parallite\ParalliteClient;

// Create Parallite client instance
// Option 1: Auto-detect socket path (recommended)
$client = new ParalliteClient();

// Option 2: Manual daemon with custom socket path
// $client = new ParalliteClient('/tmp/parallite-custom.sock');

// Option 3: Automatic daemon management
// $client = new ParalliteClient(autoManageDaemon: true);

echo "=== Parallite Client - Examples ===\n\n";

try {
    // Example 1: Basic async/await
    echo "1. Basic async/await\n";
    echo "--------------------\n";
    $start = microtime(true);

    $future1 = $client->async(function () {
        sleep(1);
        return 'Task 1 completed (1s)';
    });

    $future2 = $client->async(function () {
        sleep(2);
        return 'Task 2 completed (2s)';
    });

    $future3 = $client->async(function () {
        sleep(3);
        return 'Task 3 completed (3s)';
    });

    echo "   ✓ All tasks submitted (sockets kept open)\n";

    $result1 = $client->await($future1);
    $result2 = $client->await($future2);
    $result3 = $client->await($future3);

    $duration = round(microtime(true) - $start, 2);

    echo "   Results:\n";
    echo "     - {$result1}\n";
    echo "     - {$result2}\n";
    echo "     - {$result3}\n";
    echo "   Total time: {$duration}s\n";

    if ($duration < 4) {
        echo "   ✓ PARALLEL execution confirmed! (would be 6s if sequential)\n";
        echo "   Speedup: " . round(6 / $duration, 2) . "x\n";
    }

    echo "\n";

    // Example 2: Using awaitAll()
    echo "2. Using awaitAll() convenience method\n";
    echo "---------------------------------------\n";
    $start = microtime(true);

    $results = $client->awaitAll([
        fn() => sleep(1) && 'Task A (1s)',
        fn() => sleep(1) && 'Task B (1s)',
        fn() => sleep(1) && 'Task C (1s)',
    ]);

    $duration = round(microtime(true) - $start, 2);

    echo "   Results: " . json_encode($results) . "\n";
    echo "   Total time: {$duration}s (would be 3s if sequential)\n\n";

    // Example 3: Working with data
    echo "3. Working with data\n";
    echo "--------------------\n";

    $numbers = [1, 2, 3, 4, 5];
    $futures = [];

    foreach ($numbers as $n) {
        $futures[] = $client->async(function () use ($n) {
            usleep(100000); // 100ms
            return $n * $n;
        });
    }

    $squares = [];
    foreach ($futures as $future) {
        $squares[] = $client->await($future);
    }

    echo "   Numbers: " . json_encode($numbers) . "\n";
    echo "   Squares: " . json_encode($squares) . "\n\n";

    // Example 4: Error handling
    echo "4. Error handling\n";
    echo "------------------\n";

    try {
        $errorFuture = $client->async(function () {
            throw new RuntimeException('Simulated error');
        });

        $client->await($errorFuture);
    } catch (RuntimeException $e) {
        echo "   ✓ Caught error: {$e->getMessage()}\n\n";
    }

    echo "=== All examples completed successfully! ===\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
