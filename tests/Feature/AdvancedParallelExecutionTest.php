<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('Advanced Parallel Execution', function () {
    beforeEach(function () {
        $this->client = new ParalliteClient(autoManageDaemon: true);
    });

    afterEach(function () {
        $this->client->stopDaemon();
    });

    it('processes data in parallel chunks', function () {
        $data = range(1, 20);
        $chunkSize = 5;
        $chunks = array_chunk($data, $chunkSize);

        $start = microtime(true);

        $tasks = [];
        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk) {
                usleep(50000); // 50ms
                return array_map(fn ($n) => $n * $n, $chunk);
            };
        }
        
        $processedChunks = $this->client->awaitAll($tasks);

        $duration = microtime(true) - $start;

        $allResults = array_merge(...$processedChunks);

        expect($allResults)
            ->toHaveCount(20)
            ->and($allResults[0])->toBe(1)
            ->and($allResults[19])->toBe(400)
            ->and($duration)->toBeLessThan(0.20); // Should be ~50ms, not 200ms (with overhead)
    });

    it('handles mixed success and failure scenarios', function () {
        $results = [];
        $errors = [];

        $futures = [
            $this->client->async(fn () => 'success 1'),
            $this->client->async(function () {
                throw new RuntimeException('error 1');
            }),
            $this->client->async(fn () => 'success 2'),
            $this->client->async(function () {
                throw new RuntimeException('error 2');
            }),
        ];

        foreach ($futures as $i => $future) {
            try {
                $results[$i] = $this->client->await($future);
            } catch (RuntimeException $e) {
                $errors[$i] = $e->getMessage();
            }
        }

        expect($results)
            ->toHaveKey(0, 'success 1')
            ->toHaveKey(2, 'success 2')
            ->and($errors)
            ->toHaveKey(1, 'Task failed (awaitTask): error 1')
            ->toHaveKey(3, 'Task failed (awaitTask): error 2');
    });

    it('processes multiple file operations in parallel', function () {
        $files = ['file1.txt', 'file2.txt', 'file3.txt', 'file4.txt'];

        $tasks = [];
        foreach ($files as $file) {
            $tasks[] = function () use ($file) {
                usleep(50000); // Simulate file processing
                return [
                    'file' => $file,
                    'size' => strlen($file) * 100,
                    'processed' => true,
                ];
            };
        }
        
        $fileResults = $this->client->awaitAll($tasks);

        expect($fileResults)
            ->toHaveCount(4)
            ->and($fileResults[0]['file'])->toBe('file1.txt')
            ->and($fileResults[0]['processed'])->toBe(true)
            ->and($fileResults[3]['file'])->toBe('file4.txt');
    });

    it('handles complex data structures', function () {
        $results = $this->client->awaitAll([
            fn () => ['name' => 'John', 'age' => 30],
            fn () => ['name' => 'Jane', 'age' => 25],
            fn () => ['name' => 'Bob', 'age' => 35],
        ]);

        expect($results)
            ->toHaveCount(3)
            ->and($results[0])->toHaveKeys(['name', 'age'])
            ->and($results[0]['name'])->toBe('John')
            ->and($results[1]['name'])->toBe('Jane')
            ->and($results[2]['name'])->toBe('Bob');
    });

    it('executes tasks with varying execution times', function () {
        $start = microtime(true);

        $results = $this->client->awaitAll([
            function () {
                usleep(100000);
                return 'slow';
            },
            function () {
                usleep(10000);
                return 'fast';
            },
            function () {
                usleep(50000);
                return 'medium';
            },
        ]);

        $duration = microtime(true) - $start;

        expect($results)
            ->toBe(['slow', 'fast', 'medium'])
            ->and($duration)->toBeLessThan(0.20); // Limited by slowest task (100ms) + overhead
    });

    it('handles large number of parallel tasks', function () {
        $taskCount = 50;
        $tasks = [];
        for ($i = 0; $i < $taskCount; $i++) {
            $tasks[] = function () {
                usleep(10000);
                return 'done';
            };
        }

        $start = microtime(true);
        $results = $this->client->awaitAll($tasks);
        $duration = microtime(true) - $start;

        expect($results)
            ->toHaveCount($taskCount)
            ->and(array_unique($results))->toBe(['done']);
    });

    it('preserves order with sequential await calls', function () {
        $futures = [];
        for ($i = 1; $i <= 10; $i++) {
            $futures[] = $this->client->async(function () use ($i) {
                usleep(rand(10000, 50000));
                return $i;
            });
        }

        $results = [];
        foreach ($futures as $future) {
            $results[] = $this->client->await($future);
        }

        expect($results)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    });
});
