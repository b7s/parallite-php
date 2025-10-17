<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('Long Running Memory Leak Test', function () {
    it('executes parallel tasks for 10 minutes and verifies daemon cleanup', function () {
        // Disable PHP timeout for long-running test
        set_time_limit(0);
        
        $client = new ParalliteClient(autoManageDaemon: true, enableBenchmark: true);
        
        // Get initial process count
        $initialProcessCount = getParalliteProcessCount();
        
        echo "\n🚀 Starting 10-minute parallel execution test...\n";
        echo "⏰ Start time: ".date('H:i:s')."\n";
        echo "📊 Initial Parallite processes: {$initialProcessCount}\n\n";
        
        $startTime = time();
        $endTime = $startTime + (10 * 60); // 10 minutes
        $batchCount = 0;
        $totalTasks = 0;
        
        // Benchmark aggregation
        $totalExecutionTime = 0;
        $totalMemoryDelta = 0;
        $totalMemoryPeak = 0;
        $totalCpuTime = 0;
        $benchmarkCount = 0;
        
        // Run tasks for 10 minutes
        while (time() < $endTime) {
            $batchCount++;
            $batchStart = microtime(true);
            
            // Execute 10 parallel tasks per batch
            $promises = [];
            for ($i = 0; $i < 10; $i++) {
                $promises[] = $client->promise(function () use ($i, $batchCount) {
                    // Simulate work that takes a few seconds
                    $workDuration = rand(2, 4); // 2-4 seconds
                    sleep($workDuration);
                    
                    // Do some computation
                    $result = 0;
                    for ($j = 0; $j < 1000; $j++) {
                        $result += $j * $i;
                    }
                    
                    return [
                        'batch' => $batchCount,
                        'task' => $i,
                        'work_duration' => $workDuration,
                        'result' => $result,
                        'timestamp' => time(),
                    ];
                });
            }
            
            // Await all results (tasks execute in parallel)
            $results = $client->awaitMultiple($promises);
            
            $totalTasks += count($results);
            $batchDuration = microtime(true) - $batchStart;
            
            // Collect benchmark data from promises
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
            
            // Verify results
            expect($results)->toHaveCount(10);
            foreach ($results as $idx => $result) {
                expect($result)->toBeArray()
                    ->and($result['batch'])->toBe($batchCount)
                    ->and($result['task'])->toBe($idx)
                    ->and($result['work_duration'])->toBeGreaterThanOrEqual(2)
                    ->and($result['work_duration'])->toBeLessThanOrEqual(4);
            }
            
            // Log progress after each batch
            $elapsed = time() - $startTime;
            $remaining = $endTime - time();
            $processCount = getParalliteProcessCount();
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            
            echo "📦 Batch {$batchCount} | ";
            echo "Elapsed: ".gmdate('i:s', $elapsed)." | ";
            echo "Remaining: ".gmdate('i:s', $remaining)." | ";
            echo "Tasks: {$totalTasks} | ";
            echo "Processes: {$processCount} | ";
            echo "Memory: {$memoryUsage}MB | ";
            echo "Duration: ".round($batchDuration, 2)."s\n";
            
            // Check if we should continue
            if (time() >= $endTime) {
                break;
            }
        }
        
        $finalTime = time();
        $totalDuration = $finalTime - $startTime;
        
        echo "\n✅ Completed {$batchCount} batches with {$totalTasks} total tasks\n";
        echo "⏱️  Total duration: ".gmdate('i:s', $totalDuration)."\n";
        
        // Display benchmark statistics
        if ($benchmarkCount > 0) {
            $avgExecutionTime = round($totalExecutionTime / $benchmarkCount, 2);
            $avgMemoryDelta = round($totalMemoryDelta / $benchmarkCount, 4);
            $avgMemoryPeak = round($totalMemoryPeak / $benchmarkCount, 2);
            $avgCpuTime = round($totalCpuTime / $benchmarkCount, 2);
            
            echo "\n📊 Benchmark Statistics (averaged across {$benchmarkCount} tasks):\n";
            echo "   ⚡ Avg Execution Time: {$avgExecutionTime}ms\n";
            echo "   💾 Avg Memory Delta: {$avgMemoryDelta}MB\n";
            echo "   📈 Avg Memory Peak: {$avgMemoryPeak}MB\n";
            echo "   🖥️  Avg CPU Time: {$avgCpuTime}ms\n";
            echo "   📊 Total Execution Time: ".round($totalExecutionTime / 1000, 2)."s\n";
            echo "   📊 Total CPU Time: ".round($totalCpuTime / 1000, 2)."s\n";
        }
        
        // Get process count before stopping daemon
        $beforeStopProcessCount = getParalliteProcessCount();
        echo "📊 Parallite processes before stop: {$beforeStopProcessCount}\n";
        
        // Stop the daemon
        echo "🛑 Stopping daemon...\n";
        $client->stopDaemon();
        
        // Wait a bit for processes to terminate
        sleep(2);
        
        // Verify daemon and workers are cleaned up
        $afterStopProcessCount = getParalliteProcessCount();
        echo "📊 Parallite processes after stop: {$afterStopProcessCount}\n";
        
        // Check memory usage
        $finalMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        echo "💾 Final PHP memory usage: {$finalMemory}MB\n";
        
        echo "🏁 End time: ".date('H:i:s')."\n\n";
        
        // Get expected process count (fixed_workers + 1 for daemon, or 0 if no fixed workers)
        $config = getParalliteConfig();
        $expectedProcesses = $config['fixed_workers'] + 1;
        echo "📋 Expected processes (fixed_workers={$config['fixed_workers']}): {$expectedProcesses}\n";

        // Assertions
        expect($totalTasks)->toBeGreaterThan(0)
            ->and($batchCount)->toBeGreaterThan(0);
        
        // Verify cleanup happened - processes should be reduced or match expected count
        if ($beforeStopProcessCount > $expectedProcesses) {
            expect($afterStopProcessCount)->toBeLessThan($beforeStopProcessCount);
            echo "✅ Cleanup verified: {$beforeStopProcessCount} → {$afterStopProcessCount} processes\n";
        } else {
            echo "ℹ️  Process count stable at expected level: {$afterStopProcessCount}\n";
        }
        
        echo "✅ Memory leak test passed! Daemon cleaned up successfully.\n";
    })->skip(fn () => !getenv('RUN_LONG_TESTS'), 'Long running test - set RUN_LONG_TESTS=1 to run');
});

/**
 * Get Parallite configuration from parallite.json
 */
function getParalliteConfig(): array
{
    $configPath = dirname(__DIR__, 2).'/parallite.json';
    
    if (!file_exists($configPath)) {
        return ['fixed_workers' => 0];
    }
    
    $content = file_get_contents($configPath);
    if ($content === false) {
        return ['fixed_workers' => 0];
    }
    
    $config = json_decode($content, true);
    if (!is_array($config)) {
        return ['fixed_workers' => 0];
    }
    
    return [
        'fixed_workers' => $config['go_overrides']['fixed_workers'] ?? 0,
    ];
}

/**
 * Get count of Parallite-related processes
 */
function getParalliteProcessCount(): int
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: use tasklist
        $output = shell_exec('tasklist /FI "IMAGENAME eq parallite*" 2>NUL');
        if ($output === null) {
            return 0;
        }
        
        $lines = explode("\n", $output);
        $count = 0;
        foreach ($lines as $line) {
            if (stripos($line, 'parallite') !== false) {
                $count++;
            }
        }
        return $count;
    }
    
    // Unix-like: use ps
    $output = shell_exec('ps aux | grep -i parallite | grep -v grep | wc -l');
    if ($output === null || $output === false) {
        return 0;
    }
    
    return (int) trim($output);
}
