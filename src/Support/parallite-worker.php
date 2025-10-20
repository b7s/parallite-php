<?php

declare(strict_types=1);

/**
 * Parallite PHP Worker
 *
 * This worker process is spawned by the Go daemon to execute PHP closures.
 */

// Load ProjectRootFinderService first (before autoloader)
require_once dirname(__DIR__) . '/Service/Parallite/ProjectRootFinderService.php';

use MessagePack\MessagePack;
use Parallite\Service\Parallite\ProjectRootFinderService;

$projectRoot = ProjectRootFinderService::find(__DIR__);

// Load autoloader
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    error_log('[Worker] Autoloader not found at: ' . $autoloadPath);
    exit(1);
}

require_once $autoloadPath;

// Load configuration and includes
// Priority: 1. Client project root, 2. Package root
$clientConfigPath = $projectRoot . '/parallite.json';
$packageRoot = dirname(__DIR__, 2); // From src/Support/ to package root
$packageConfigPath = $packageRoot . '/parallite.json';

$configPath = file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;
$configRoot = file_exists($clientConfigPath) ? $projectRoot : $packageRoot;
$debugLogs = false;

if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    if ($configContent === false) {
        error_log('[Worker] Failed to read config file');
    } else {
        $config = json_decode($configContent, true);

        if (is_array($config)) {
            // Check if debug logs are enabled
            $debugLogs = $config['worker_debug_logs'] ?? false;

            if (isset($config['php_includes']) && is_array($config['php_includes'])) {
                foreach ($config['php_includes'] as $include) {
                    if (!is_string($include)) {
                        continue;
                    }

                    // Check if it's a full path (starts with "/" or has ":" as second character for Windows)
                    $isFullPath = str_starts_with($include, '/') || (strlen($include) > 1 && $include[1] === ':');

                    $includePath = $isFullPath ? $include : $configRoot . '/' . $include;

                    if (file_exists($includePath)) {
                        require_once $includePath;
                    }
                }
            }
        }
    }
}

// Get worker name from environment
$workerNameFromEnv = getenv('WORKER_NAME');
$workerName = $workerNameFromEnv !== false ? $workerNameFromEnv : 'worker_' . getmypid();
$defaultBenchmark = $config['enable_benchmark'] ?? false;

// Track current task ID for error handling
$currentTaskId = null;

// Log function
function workerLog(string $message): void
{
    global $workerName, $debugLogs;

    if ($debugLogs) {
        error_log("[$workerName] $message");
    }
}

// Light normalization - only convert objects, preserve arrays as-is
function lightNormalize(mixed $data, int $depth = 0): mixed
{
    if ($depth > 10) {
        return $data; // Prevent infinite recursion
    }

    if (is_object($data)) {
        // Convert objects to arrays
        if (method_exists($data, 'toArray')) {
            // Eloquent models, Collections, etc.
            $data = $data->toArray();
        } elseif ($data instanceof DateTimeInterface) {
            // DateTime objects
            return [
                '_type' => 'datetime',
                'value' => $data->format('c'),
                'timezone' => $data->getTimezone()->getName(),
            ];
        } else {
            // stdClass and other objects
            $data = (array)$data;
        }
    }

    if (is_array($data)) {
        // Recursively normalize nested values, preserving array structure
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[$key] = lightNormalize($value, $depth + 1);
        }
        return $normalized;
    }

    // Handle special float values
    if (is_float($data) && (is_nan($data) || is_infinite($data))) {
        return null;
    }

    return $data;
}

// Send error response helper
function sendErrorResponse(string $taskId, string $error): void
{
    global $workerName;

    try {
        $response = [
            'ok' => false,
            'error' => $error,
            'task_id' => $taskId,
        ];

        $responsePacked = MessagePack::pack($response);
        $responseLength = pack('N', strlen($responsePacked));

        fwrite(STDOUT, $responseLength . $responsePacked);
        fflush(STDOUT);

        error_log("[$workerName] Sent error response for task $taskId: $error");
    } catch (Throwable $e) {
        error_log("[$workerName] Failed to send error response: {$e->getMessage()}");
    }
}

// Register shutdown function to catch fatal errors
register_shutdown_function(function () {
    global $currentTaskId, $workerName;

    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $errorMsg = "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log("[$workerName] $errorMsg");

        if ($currentTaskId !== null) {
            sendErrorResponse($currentTaskId, $errorMsg);
        }
    }
});

// Set error handler for non-fatal errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    global $workerName;

    // Don't handle suppressed errors (@)
    if ((error_reporting() & $errno) === 0) {
        return false;
    }

    error_log("[$workerName] PHP Error [$errno]: $errstr in $errfile on line $errline");

    return false; // Let PHP handle it normally
});

workerLog('Worker started');

// Main loop: read from stdin, execute, write to stdout
while (true) {
    // Read 4-byte length prefix
    $lengthData = fread(STDIN, 4);

    if ($lengthData === false || strlen($lengthData) !== 4) {
        workerLog('Failed to read length, exiting');
        break;
    }

    $unpacked = unpack('N', $lengthData);
    if ($unpacked === false) {
        workerLog('Failed to unpack length, exiting');
        break;
    }
    $length = $unpacked[1];

    // Read payload
    $payload = '';
    $remaining = $length;

    while ($remaining > 0) {
        $chunk = fread(STDIN, $remaining);

        if ($chunk === false) {
            workerLog('Failed to read payload, exiting');
            break 2;
        }

        $payload .= $chunk;
        $remaining -= strlen($chunk);
    }

    // Decode request
    try {
        $request = MessagePack::unpack($payload);
    } catch (Throwable $e) {
        workerLog('Invalid request: failed to unpack MessagePack - ' . $e->getMessage());
        continue;
    }

    if (!is_array($request)) {
        workerLog('Invalid request: not a valid array');
        continue;
    }

    if (!isset($request['task_id'])) {
        workerLog('Invalid request: missing task_id');
        continue;
    }

    $taskId = $request['task_id'];
    if (!is_string($taskId)) {
        workerLog('Invalid request: task_id is not a string');
        continue;
    }

    // Track current task for error handling
    $currentTaskId = $taskId;

    // Check if request has payload (closure) or other format
    if (!isset($request['payload'])) {
        workerLog("Invalid request for task $taskId: missing payload (closure expected)");

        // Send error response
        $response = [
            'ok' => false,
            'error' => 'Missing payload field. This worker expects serialized closures.',
            'task_id' => $taskId,
        ];

        try {
            $responsePacked = MessagePack::pack($response);
        } catch (Throwable $e) {
            workerLog("Failed to pack response: {$e->getMessage()}");
            continue;
        }
        $responseLength = pack('N', strlen($responsePacked));
        fwrite(STDOUT, $responseLength . $responsePacked);
        fflush(STDOUT);

        continue;
    }

    if (!is_string($request['payload'])) {
        workerLog("Invalid request for task {$taskId}: payload is not a string");
        continue;
    }

    $serialized = $request['payload'];

    $context = $request['context'] ?? [];

    workerLog("Executing task: $taskId");

    // Determine if benchmark is enabled for this task
    // Priority: request field > config default
    $benchmarkEnabled = $request['enable_benchmark'] ?? $defaultBenchmark;

    $benchmarkSource = isset($request['enable_benchmark']) ? 'request' : 'config';
    workerLog('Benchmark enabled: ' . ($benchmarkEnabled ? 'true' : 'false') . " (source: $benchmarkSource)");

    // Initialize benchmark metrics
    $benchmark = null;
    $startTime = 0.0;
    $startMemory = 0;
    $startRusage = null;

    if ($benchmarkEnabled) {
        $startTime = microtime(true);
        // Use real memory (not allocated blocks) for delta calculation
        $startMemory = memory_get_usage(false);
        $startMemoryAllocated = memory_get_usage(true);
        // Reset peak memory before task execution
        memory_reset_peak_usage();
        $startRusage = getrusage();
    }

    try {
        // Deserialize closure
        workerLog('Deserializing closure...');
        $closure = \Opis\Closure\unserialize($serialized);
        workerLog('Closure deserialized successfully');

        if (!$closure instanceof Closure) {
            throw new RuntimeException('Deserialized payload is not a closure');
        }

        // Execute closure
        workerLog('Executing closure...');
        $result = $closure();

        // Capture memory immediately after execution (before GC)
        $memoryAfterExecution = memory_get_usage(false);
        $peakAfterExecution = memory_get_peak_usage(false);

        workerLog('Closure executed successfully');

        // Normalize result to handle complex objects (Eloquent, Collections, DateTime)
        // This preserves array keys but converts objects to arrays
        try {
            $result = lightNormalize($result);
        } catch (Throwable $e) {
            workerLog("Failed to normalize result: {$e->getMessage()}");
            // Continue with original result
        }

        // Collect benchmark metrics if enabled
        if ($benchmarkEnabled) {
            $endTime = microtime(true);
            // Use the memory captured immediately after execution
            $endMemory = $memoryAfterExecution;
            $endPeakMemory = $peakAfterExecution;
            $endRusage = getrusage();

            // Calculate execution time in milliseconds
            $executionTimeMs = ($endTime - $startTime) * 1000;

            // Calculate memory usage (delta during task execution)
            // Use real memory usage, not allocated blocks
            $memoryDelta = $endMemory - $startMemory;
            // Peak memory is already reset before task, so just use the current peak
            $peakMemoryUsed = $endPeakMemory;

            // Calculate CPU time (user + system) in milliseconds
            $totalCpuTimeMs = 0.0;
            if (is_array($startRusage) && is_array($endRusage)) {
                $userTimeMsStart = (is_numeric($startRusage['ru_utime.tv_sec'] ?? 0) ? (int)$startRusage['ru_utime.tv_sec'] : 0) * 1000 + (is_numeric($startRusage['ru_utime.tv_usec'] ?? 0) ? (int)$startRusage['ru_utime.tv_usec'] : 0) / 1000;
                $systemTimeMsStart = (is_numeric($startRusage['ru_stime.tv_sec'] ?? 0) ? (int)$startRusage['ru_stime.tv_sec'] : 0) * 1000 + (is_numeric($startRusage['ru_stime.tv_usec'] ?? 0) ? (int)$startRusage['ru_stime.tv_usec'] : 0) / 1000;

                $userTimeMsEnd = (is_numeric($endRusage['ru_utime.tv_sec'] ?? 0) ? (int)$endRusage['ru_utime.tv_sec'] : 0) * 1000 + (is_numeric($endRusage['ru_utime.tv_usec'] ?? 0) ? (int)$endRusage['ru_utime.tv_usec'] : 0) / 1000;
                $systemTimeMsEnd = (is_numeric($endRusage['ru_stime.tv_sec'] ?? 0) ? (int)$endRusage['ru_stime.tv_sec'] : 0) * 1000 + (is_numeric($endRusage['ru_stime.tv_usec'] ?? 0) ? (int)$endRusage['ru_stime.tv_usec'] : 0) / 1000;

                $userTimeMs = $userTimeMsEnd - $userTimeMsStart;
                $systemTimeMs = $systemTimeMsEnd - $systemTimeMsStart;
                $totalCpuTimeMs = $userTimeMs + $systemTimeMs;
            }

            $benchmark = [
                'execution_time_ms' => round($executionTimeMs, 3),
                'memory_delta_mb' => round($memoryDelta / 1024 / 1024, 4),
                'memory_peak_mb' => round($peakMemoryUsed / 1024 / 1024, 4),
                'cpu_time_ms' => round($totalCpuTimeMs, 3),
            ];
        }

        // Serialize result
        $response = [
            'ok' => true,
            'result' => $result,
            'task_id' => $taskId,
        ];

        if ($benchmark !== null) {
            $response['benchmark'] = $benchmark;
        }

        workerLog("Task $taskId completed successfully");
    } catch (Throwable $e) {
        // Return error
        $response = [
            'ok' => false,
            'error' => $e->getMessage(),
            'task_id' => $taskId,
        ];

        workerLog("Task $taskId failed: " . $e->getMessage());
    }

    // Send response
    workerLog("Sending response for task: {$taskId}");
    try {
        $responsePacked = MessagePack::pack($response);
    } catch (Throwable $e) {
        workerLog("Failed to pack response for task {$taskId}: {$e->getMessage()}");
        continue;
    }
    $responseLength = pack('N', strlen($responsePacked));

    fwrite(STDOUT, $responseLength . $responsePacked);
    fflush(STDOUT);
    workerLog("Response sent for task: $taskId");

    // Clear current task after completion
    $currentTaskId = null;
}

workerLog('Parallite Worker shutting down');
