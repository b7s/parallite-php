<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

if (getenv('RUN_LONG_TESTS')) {
    echo "...\n";
    echo "Starting 10-minute parallel execution test...\n";
    echo 'Start time: '.date('H:i:s')."\n";
}

describe('Long Running Memory Leak Test', function () {
    it('executes parallel tasks for 10 minutes and verifies cleanup', function () {
        set_time_limit(0);

        $maxMemoryMb = 64;

        $client = new ParalliteClient(enableBenchmark: true);

        $startTime = time();
        $endTime = $startTime + (10 * 60);
        $batchCount = 0;
        $totalTasks = 0;

        $totalExecutionTime = 0;
        $totalMemoryDelta = 0;
        $totalMemoryPeak = 0;
        $totalCpuTime = 0;
        $benchmarkCount = 0;

        while (time() < $endTime) {
            $batchCount++;
            $batchStart = microtime(true);

            $promises = [];
            for ($i = 0; $i < 10; $i++) {
                $promises[] = $client->promise(function () use ($i, $batchCount, $maxMemoryMb) {
                    $targetMemoryMb = random_int(1, $maxMemoryMb);
                    $memoryData = [];

                    $chunkSize = 1024 * 100;
                    $chunksNeeded = (int) (($targetMemoryMb * 1024 * 1024) / $chunkSize);

                    for ($m = 0; $m < $chunksNeeded; $m++) {
                        $memoryData[] = str_repeat('x', $chunkSize);
                    }

                    $workDuration = random_int(2, 4);
                    $cpuWorkMs = (int) ($workDuration * 1000 * (2 / 100));
                    $cpuWorkMs = max($cpuWorkMs, 50);

                    $startTime = microtime(true);
                    $cpuEndTime = $startTime + ($cpuWorkMs / 1000);

                    $result = 0;
                    while (microtime(true) < $cpuEndTime) {
                        for ($j = 0; $j < 10000; $j++) {
                            $result += sqrt($j * $i + 1);
                            $result = $result % 1000000;
                        }
                    }

                    $elapsed = microtime(true) - $startTime;
                    $remainingSleep = $workDuration - $elapsed;
                    if ($remainingSleep > 0) {
                        usleep((int) ($remainingSleep * 1000000));
                    }

                    $actualMemoryMb = round(memory_get_usage(true) / 1024 / 1024, 2);

                    return [
                        'batch' => $batchCount,
                        'task' => $i,
                        'work_duration' => $workDuration,
                        'target_memory_mb' => $targetMemoryMb,
                        'actual_memory_mb' => $actualMemoryMb,
                        'cpu_work_ms' => $cpuWorkMs,
                        'result' => (int) $result,
                        'timestamp' => time(),
                    ];
                });
            }

            $results = $client->awaitMultiple($promises);

            $totalTasks += count($results);
            $batchDuration = microtime(true) - $batchStart;

            foreach ($promises as $promise) {
                $benchmark = $promise->getBenchmark();
                if ($benchmark) {
                    $totalExecutionTime += $benchmark->executionTimeMs;
                    $totalMemoryDelta += $benchmark->memoryDeltaMb;
                    $totalMemoryPeak += $benchmark->memoryPeakMb;
                    $totalCpuTime += $benchmark->cpuTimeMs;
                    $benchmarkCount++;
                }
            }

            expect($results)->toHaveCount(10);
            foreach ($results as $idx => $result) {
                expect($result)->toBeArray()
                    ->and($result['batch'])->toBe($batchCount)
                    ->and($result['task'])->toBe($idx)
                    ->and($result['work_duration'])->toBeGreaterThanOrEqual(2)
                    ->and($result['work_duration'])->toBeLessThanOrEqual(4)
                    ->and($result['target_memory_mb'])->toBeGreaterThanOrEqual(1)
                    ->and($result['target_memory_mb'])->toBeLessThanOrEqual($maxMemoryMb)
                    ->and($result['cpu_work_ms'])->toBeGreaterThanOrEqual(50);
            }

            $elapsed = time() - $startTime;
            $remaining = $endTime - time();
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);

            echo "Batch {$batchCount} | Elapsed: ".gmdate('i:s', $elapsed).' | Remaining: '.gmdate('i:s', $remaining)." | Tasks: {$totalTasks} | Memory: {$memoryUsage}MB | Duration: ".round($batchDuration, 2)."s\n";

            if (time() >= $endTime) {
                break;
            }
        }

        $totalDuration = time() - $startTime;

        echo "\nCompleted {$batchCount} batches with {$totalTasks} total tasks\n";
        echo 'Total duration: '.gmdate('i:s', $totalDuration)."\n";

        if ($benchmarkCount > 0) {
            $avgExecutionTime = round($totalExecutionTime / $benchmarkCount, 2);
            $avgMemoryDelta = round($totalMemoryDelta / $benchmarkCount, 4);
            $avgMemoryPeak = round($totalMemoryPeak / $benchmarkCount, 2);
            $avgCpuTime = round($totalCpuTime / $benchmarkCount, 2);

            echo "\nBenchmark Statistics (averaged across {$benchmarkCount} tasks):\n";
            echo "  Avg Execution Time: {$avgExecutionTime}ms\n";
            echo "  Avg Memory Delta: {$avgMemoryDelta}MB\n";
            echo "  Avg Memory Peak: {$avgMemoryPeak}MB\n";
            echo "  Avg CPU Time: {$avgCpuTime}ms\n";
        }

        $finalMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        echo "Final PHP memory usage: {$finalMemory}MB\n";
        echo 'End time: '.date('H:i:s')."\n\n";

        expect($totalTasks)->toBeGreaterThan(0)
            ->and($batchCount)->toBeGreaterThan(0);
    })->skip(fn () => ! getenv('RUN_LONG_TESTS'), 'Long running test - set RUN_LONG_TESTS=1 to run');
});
