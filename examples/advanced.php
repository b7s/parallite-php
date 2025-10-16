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
 */

require __DIR__.'/../vendor/autoload.php';

use Parallite\ParalliteClient;

// Use automatic daemon management with auto-detected socket path
$client = new ParalliteClient(autoManageDaemon: true);

echo "=== Advanced Parallite Examples ===\n\n";

// Example 1: Parallel API Calls
echo "1. Parallel API Calls\n";
echo "---------------------\n";

$start = microtime(true);

$apiResults = $client->awaitAll([
    fn() => [
        'service' => 'users',
        'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/users/1'), true),
        'time' => microtime(true)
    ],
    fn() => [
        'service' => 'posts',
        'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/posts/1'), true),
        'time' => microtime(true)
    ],
    fn() => [
        'service' => 'comments',
        'data' => json_decode(file_get_contents('https://jsonplaceholder.typicode.com/comments/1'), true),
        'time' => microtime(true)
    ],
]);

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

$closures = [];
foreach ($chunks as $chunk) {
    $closures[] = function () use ($chunk) {
        // Simulate heavy processing
        usleep(100000); // 100ms
        return array_map(fn($n) => $n * $n, $chunk);
    };
}

$processedChunks = $client->awaitAll($closures);

$duration = round(microtime(true) - $start, 2);

$allResults = array_merge(...$processedChunks);

echo "   Processed " . count($data) . " items in " . count($chunks) . " chunks\n";
echo "   Duration: {$duration}s\n";
echo "   Results: " . implode(', ', array_slice($allResults, 0, 10)) . "...\n\n";

// Example 3: Error Handling with Retry
echo "3. Error Handling with Retry\n";
echo "-----------------------------\n";

function executeWithRetry(ParalliteClient $client, Closure $task, int $maxRetries = 3): mixed
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $future = $client->async($task);
            return $client->await($future);
        } catch (RuntimeException $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            echo "   Retry attempt {$attempt}/{$maxRetries}...\n";
            usleep(100000); // Wait 100ms before retry
        }
    }
    
    throw new RuntimeException('Max retries exceeded');
}

try {
    $result = executeWithRetry($client, function () {
        // Simulate flaky operation
        if (rand(1, 3) === 1) {
            return 'Success!';
        }
        throw new RuntimeException('Simulated failure');
    });
    
    echo "   ✓ Task completed: {$result}\n\n";
} catch (RuntimeException $e) {
    echo "   ✗ Task failed after retries: {$e->getMessage()}\n\n";
}

// Example 4: Progress Monitoring
echo "4. Progress Monitoring\n";
echo "-----------------------\n";

$tasks = range(1, 10);
$futures = [];

echo "   Submitting tasks: ";
foreach ($tasks as $i) {
    $futures[] = $client->async(function () use ($i) {
        usleep(rand(100000, 500000)); // Random delay 100-500ms
        return "Task {$i} completed";
    });
    echo ".";
}
echo " Done!\n";

echo "   Collecting results: ";
$results = [];
foreach ($futures as $i => $future) {
    $results[] = $client->await($future);
    echo ".";
}
echo " Done!\n";

echo "   Completed " . count($results) . " tasks\n\n";

// Example 5: Parallel File Processing
echo "5. Parallel File Processing\n";
echo "----------------------------\n";

$files = ['file1.txt', 'file2.txt', 'file3.txt'];

$fileClosures = [];
foreach ($files as $file) {
    $fileClosures[] = function () use ($file) {
        // Simulate file processing
        usleep(200000); // 200ms
        return [
            'file' => $file,
            'size' => rand(1000, 10000),
            'lines' => rand(10, 100),
            'processed' => true
        ];
    };
}

$fileResults = $client->awaitAll($fileClosures);

echo "   Processed " . count($fileResults) . " files:\n";
foreach ($fileResults as $result) {
    echo "   - {$result['file']}: {$result['lines']} lines, {$result['size']} bytes\n";
}

echo "\n=== All advanced examples completed! ===\n";
