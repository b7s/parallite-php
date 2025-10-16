<?php

declare(strict_types=1);

/**
 * Advanced Parallite Example - Real-World Use Cases
 * 
 * This example demonstrates advanced usage patterns including:
 * - Parallel API calls
 * - Data processing pipelines
 * - Error handling strategies
 * - Performance monitoring
 * 
 * Uses the global async() and await() functions - no setup required!
 */

require __DIR__.'/../vendor/autoload.php';

// No imports needed - async() and await() are available globally!

echo "=== Advanced Parallite Examples ===\n\n";

// Example 1: Parallel API Calls
echo "1. Parallel API Calls\n";
echo "---------------------\n";

$start = microtime(true);

$p1 = async(fn() => [
    'service' => 'users',
    'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/users/1'), true),
]);

$p2 = async(fn() => [
    'service' => 'posts',
    'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/posts/1'), true),
]);

$p3 = async(fn() => [
    'service' => 'comments',
    'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/comments/1'), true),
]);

$apiResults = [await($p1), await($p2), await($p3)];

$duration = round(microtime(true) - $start, 2);

echo "   Fetched " . count($apiResults) . " APIs in {$duration}s\n";
foreach ($apiResults as $result) {
    echo "   - {$result['service']}: " . ($result['data']['id'] ?? 'N/A') . "\n";
}
echo "\n";

// Example 2: Data Processing Pipeline
echo "2. Data Processing Pipeline\n";
echo "----------------------------\n";

$data = range(1, 20);
$chunkSize = 5;
$chunks = array_chunk($data, $chunkSize);

$start = microtime(true);

$promises = [];
foreach ($chunks as $chunk) {
    $promises[] = async(function () use ($chunk) {
        // Simulate heavy processing
        usleep(100000); // 100ms
        return array_map(fn($n) => $n * $n, $chunk);
    });
}

$processedChunks = array_map(fn($p) => await($p), $promises);

$duration = round(microtime(true) - $start, 2);

$allResults = array_merge(...$processedChunks);

echo "   Processed " . count($data) . " items in " . count($chunks) . " chunks\n";
echo "   Duration: {$duration}s\n";
echo "   Results: " . implode(', ', array_slice($allResults, 0, 10)) . "...\n\n";

// Example 3: Error Handling with catch()
echo "3. Error Handling with catch()\n";
echo "-------------------------------\n";

$result = await(
    async(function () {
        // Simulate flaky operation
        if (rand(1, 3) === 1) {
            return 'Success!';
        }
        throw new RuntimeException('Simulated failure');
    })->catch(fn($e) => 'Recovered from: ' . $e->getMessage())
);

echo "   Result: {$result}\n\n";

// Example 4: Progress Monitoring
echo "4. Progress Monitoring\n";
echo "-----------------------\n";

$tasks = range(1, 15);
$promises = [];

echo "   Submitting tasks: ";
foreach ($tasks as $i) {
    $promises[] = async(function () use ($i) {
        usleep(rand(100000, 500000)); // Random delay 100-500ms
        return "Task {$i} completed";
    });
    echo ".";
}
echo " Done!\n";

echo "   Collecting results: ";
$results = [];
foreach ($promises as $promise) {
    $results[] = await($promise);
    echo ".";
}
echo " Done!\n";

echo "   Completed " . count($results) . " tasks\n\n";

// Example 5: Parallel File Processing with then()
echo "5. Parallel File Processing with then()\n";
echo "----------------------------------------\n";

$files = ['file1.txt', 'file2.txt', 'file3.txt'];

$filePromises = [];
foreach ($files as $file) {
    $filePromises[] = async(function () use ($file) {
        // Simulate file processing
        usleep(200000); // 200ms
        return [
            'file' => $file,
            'size' => rand(1000, 10000),
            'lines' => rand(10, 100),
        ];
    })->then(fn($data) => array_merge($data, ['processed' => true]));
}

$fileResults = array_map(fn($p) => await($p), $filePromises);

echo "   Processed " . count($fileResults) . " files:\n";
foreach ($fileResults as $result) {
    echo "   - {$result['file']}: {$result['lines']} lines, {$result['size']} bytes\n";
}

echo "\n=== All advanced examples completed! ===\n";
