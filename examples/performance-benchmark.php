<?php

declare(strict_types=1);

/**
 * Performance Benchmark - Using Fork Benchmark Data
 *
 * This example demonstrates:
 * - Collecting benchmark data from forked child processes
 * - Analyzing execution time, memory, and CPU usage per task
 * - Comparing performance across different workload types
 * - Understanding memory behavior in forked processes
 */

require __DIR__.'/../vendor/autoload.php';

echo "=== Performance Benchmark ===\n\n";
echo "This benchmark collects performance data from forked child processes.\n";
echo "Each task is measured for execution time, memory usage, and CPU time.\n\n";

$timeStart = microtime(true);

// Test 1: Light workload (small, fast tasks)
echo "1️⃣  🪶 Light Workload (20 small tasks)\n";
echo '   '.str_repeat('─', 50)."\n";

$promises = [];
for ($i = 1; $i <= 20; $i++) {
    $promises[] = async(function () use ($i) {
        usleep(10000); // 10ms

        return $i * 2;
    }, enableBenchmark: true);
}

$start = microtime(true);
$results = array_map(fn ($p) => await($p), $promises);
$duration = microtime(true) - $start;

// Collect benchmark data
$benchmarks = array_map(fn ($p) => $p->getBenchmark(), $promises);
$avgExecTime = array_sum(array_map(fn ($b) => $b?->executionTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$avgCpuTime = array_sum(array_map(fn ($b) => $b?->cpuTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$totalMemDelta = array_sum(array_map(fn ($b) => $b?->memoryDeltaMb ?? 0, $benchmarks));
$maxPeak = max(array_map(fn ($b) => $b?->memoryPeakMb ?? 0, $benchmarks));

echo '   ✓ Completed: '.count($results)." tasks\n";
echo '   ⏱️  Total Duration: '.round($duration, 2)."s\n";
echo '   🚀 Throughput: '.round(count($results) / max($duration, 0.01), 2)." tasks/s\n";
echo '   📊 Avg Execution Time: '.round($avgExecTime, 2)."ms per task\n";
echo '   💻 Avg CPU Time: '.round($avgCpuTime, 2)."ms per task\n";
echo '   💾 Total Memory Delta: '.round($totalMemDelta, 4)."MB\n";
echo '   📈 Max Memory Peak: '.round($maxPeak, 4)."MB\n";
echo '   📈 Sum: '.array_sum($results)."\n\n";

// Test 2: CPU-intensive workload
echo "2️⃣  🔥 CPU-Intensive Workload (15 tasks)\n";
echo '   '.str_repeat('─', 50)."\n";

$promises = [];
for ($i = 1; $i <= 15; $i++) {
    $promises[] = async(function () use ($i) {
        // CPU-intensive calculation
        $result = 0;
        for ($j = 0; $j < 100000; $j++) {
            $result += sqrt($j);
        }

        return $i;
    }, enableBenchmark: true);
}

$start = microtime(true);
$results = array_map(fn ($p) => await($p), $promises);
$duration = microtime(true) - $start;

// Collect benchmark data
$benchmarks = array_map(fn ($p) => $p->getBenchmark(), $promises);
$avgExecTime = array_sum(array_map(fn ($b) => $b?->executionTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$avgCpuTime = array_sum(array_map(fn ($b) => $b?->cpuTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$totalMemDelta = array_sum(array_map(fn ($b) => $b?->memoryDeltaMb ?? 0, $benchmarks));
$maxPeak = max(array_map(fn ($b) => $b?->memoryPeakMb ?? 0, $benchmarks));

echo '   ✓ Completed: '.count($results)." tasks\n";
echo '   ⏱️  Total Duration: '.round($duration, 2)."s\n";
echo '   🚀 Throughput: '.round(count($results) / max($duration, 0.01), 2)." tasks/s\n";
echo '   📊 Avg Execution Time: '.round($avgExecTime, 2)."ms per task\n";
echo '   💻 Avg CPU Time: '.round($avgCpuTime, 2)."ms per task\n";
echo '   💾 Total Memory Delta: '.round($totalMemDelta, 4)."MB\n";
echo '   📈 Max Memory Peak: '.round($maxPeak, 4)."MB\n\n";

// Test 3: Memory-intensive workload
echo "3️⃣  🧠 Memory-Intensive Workload (10 tasks)\n";
echo '   '.str_repeat('─', 50)."\n";

$promises = [];
for ($i = 1; $i <= 10; $i++) {
    $promises[] = async(function () {
        // Allocate memory
        $data = array_fill(0, 5000000, 'x'); // ~10MB
        usleep(20000); // Keep data in memory

        return count($data);
    }, enableBenchmark: true);
}

$start = microtime(true);
$results = array_map(fn ($p) => await($p), $promises);
$duration = microtime(true) - $start;

// Collect benchmark data
$benchmarks = array_map(fn ($p) => $p->getBenchmark(), $promises);
$avgExecTime = array_sum(array_map(fn ($b) => $b?->executionTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$avgCpuTime = array_sum(array_map(fn ($b) => $b?->cpuTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$totalMemDelta = array_sum(array_map(fn ($b) => $b?->memoryDeltaMb ?? 0, $benchmarks));
$maxPeak = max(array_map(fn ($b) => $b?->memoryPeakMb ?? 0, $benchmarks));

echo '   ✓ Completed: '.count($results)." tasks\n";
echo '   ⏱️  Total Duration: '.round($duration, 2)."s\n";
echo '   🚀 Throughput: '.round(count($results) / max($duration, 0.01), 2)." tasks/s\n";
echo '   📊 Avg Execution Time: '.round($avgExecTime, 2)."ms per task\n";
echo '   💻 Avg CPU Time: '.round($avgCpuTime, 2)."ms per task\n";
echo '   💾 Total Memory Delta: '.round($totalMemDelta, 4)."MB\n";
echo '   📈 Max Memory Peak: '.round($maxPeak, 4)."MB\n";
echo '   📦 Total items processed: '.array_sum($results)."\n\n";

// Test 4: Mixed workload
echo "4️⃣  🎭 Mixed Workload (20 tasks)\n";
echo '   '.str_repeat('─', 50)."\n";

$promises = [];
for ($i = 1; $i <= 20; $i++) {
    $promises[] = async(function () use ($i) {
        // Mix of I/O, CPU, and memory
        usleep(5000); // 5ms I/O
        $data = array_fill(0, 10000, md5((string) $i));
        $result = 0;
        for ($j = 0; $j < 1000; $j++) {
            $result += strlen($data[$j % count($data)]);
        }

        return $result;
    }, enableBenchmark: true);
}

$start = microtime(true);
$results = array_map(fn ($p) => await($p), $promises);
$duration = microtime(true) - $start;

// Collect benchmark data
$benchmarks = array_map(fn ($p) => $p->getBenchmark(), $promises);
$avgExecTime = array_sum(array_map(fn ($b) => $b?->executionTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$avgCpuTime = array_sum(array_map(fn ($b) => $b?->cpuTimeMs ?? 0, $benchmarks)) / count($benchmarks);
$totalMemDelta = array_sum(array_map(fn ($b) => $b?->memoryDeltaMb ?? 0, $benchmarks));
$maxPeak = max(array_map(fn ($b) => $b?->memoryPeakMb ?? 0, $benchmarks));

echo '   ✓ Completed: '.count($results)." tasks\n";
echo '   ⏱️  Total Duration: '.round($duration, 2)."s\n";
echo '   🚀 Throughput: '.round(count($results) / max($duration, 0.01), 2)." tasks/s\n";
echo '   📊 Avg Execution Time: '.round($avgExecTime, 2)."ms per task\n";
echo '   💻 Avg CPU Time: '.round($avgCpuTime, 2)."ms per task\n";
echo '   💾 Total Memory Delta: '.round($totalMemDelta, 4)."MB\n";
echo '   📈 Max Memory Peak: '.round($maxPeak, 4)."MB\n\n";

// Final summary
$totalDuration = microtime(true) - $timeStart;

echo "═══════════════════════════════════════════════════\n";
echo "📈 Final Summary\n";
echo "═══════════════════════════════════════════════════\n\n";

echo '⏱️  Total Duration: '.round($totalDuration, 2)."s\n";
echo "📊 Total Tasks: 65 (20+15+10+20)\n";
echo '🚀 Overall Throughput: '.round(65 / max($totalDuration, 0.01), 2)." tasks/s\n\n";

echo "🎯 Key Insights:\n";
echo "   ⏱️  Execution time measures total task duration\n";
echo "   💻 CPU time shows actual CPU usage (can be < execution time for I/O)\n";
echo "   💾 Memory delta may be zero due to PHP's automatic cleanup\n";
echo "   📈 Memory peak captures maximum usage during task execution\n\n";

echo "Note:\n";
echo " Memory metrics may show zero for tasks where PHP automatically\n";
echo " frees memory. This is normal behavior in forked processes.\n\n";

if ($totalDuration < 5) {
    echo "✅ Benchmark completed in under 5 seconds!\n";
} else {
    echo '⏱️  Benchmark took: '.round($totalDuration, 2)."s\n";
}

echo "\n🏁 === Benchmark Complete ===\n";
