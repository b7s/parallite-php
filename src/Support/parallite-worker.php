<?php

declare(strict_types=1);

/**
 * Parallite PHP Worker
 *
 * This worker process is spawned by the Go daemon to execute PHP closures.
 */

// Find project root by looking for vendor/autoload.php
function findProjectRoot(string $startDir): ?string
{
    $dir = $startDir;
    $maxLevels = 10;
    
    for ($i = 0; $i < $maxLevels; $i++) {
        if (file_exists($dir.'/vendor/autoload.php')) {
            return $dir;
        }
        
        $parentDir = dirname($dir);
        if ($parentDir === $dir) {
            break; // Reached filesystem root
        }
        
        $dir = $parentDir;
    }
    
    return null;
}

$projectRoot = findProjectRoot(__DIR__);

if ($projectRoot === null) {
    error_log('[Worker] Failed to find project root');
    exit(1);
}

// Load autoloader
$autoloadPath = $projectRoot.'/vendor/autoload.php';
if (! file_exists($autoloadPath)) {
    error_log('[Worker] Autoloader not found at: '.$autoloadPath);
    exit(1);
}

require_once $autoloadPath;

// Load configuration and includes
// Priority: 1. Client project root, 2. Package root
$clientConfigPath = $projectRoot.'/parallite.json';
$packageRoot = dirname(dirname(__DIR__)); // From src/Support/ to package root
$packageConfigPath = $packageRoot.'/parallite.json';

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
                    $includePath = $configRoot.'/'.$include;
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
$workerName = $workerNameFromEnv !== false ? $workerNameFromEnv : 'worker_'.getmypid();

// Log function
function workerLog(string $message): void
{
    global $workerName, $debugLogs;
    
    if ($debugLogs) {
        error_log("[$workerName] $message");
    }
}

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
    $request = json_decode($payload, true);
    if (!is_array($request)) {
        workerLog('Invalid request: not a valid JSON array');
        continue;
    }

    if (! isset($request['task_id'])) {
        workerLog('Invalid request: missing task_id');
        continue;
    }

    $taskId = $request['task_id'];
    if (!is_string($taskId)) {
        workerLog('Invalid request: task_id is not a string');
        continue;
    }

    // Check if request has payload (closure) or other format
    if (! isset($request['payload'])) {
        workerLog("Invalid request for task $taskId: missing payload (closure expected)");
        
        // Send error response
        $response = [
            'ok' => false,
            'error' => 'Missing payload field. This worker expects serialized closures.',
            'task_id' => $taskId,
        ];
        
        $responseJson = json_encode($response);
        if ($responseJson === false) {
            continue;
        }
        $responseLength = pack('N', strlen($responseJson));
        fwrite(STDOUT, $responseLength.$responseJson);
        fflush(STDOUT);
        
        continue;
    }

    if (!is_string($request['payload'])) {
        workerLog("Invalid request for task {$taskId}: payload is not a string");
        continue;
    }
    
    $serialized = base64_decode($request['payload'], true);
    if ($serialized === false) {
        workerLog("Invalid request for task {$taskId}: failed to decode base64");
        continue;
    }
    
    $context = $request['context'] ?? [];

    workerLog("Executing task: $taskId");

    try {
        // Deserialize closure
        workerLog("Deserializing closure...");
        $closure = \Opis\Closure\unserialize($serialized);
        workerLog("Closure deserialized successfully");

        if (! $closure instanceof Closure) {
            throw new RuntimeException('Deserialized payload is not a closure');
        }

        // Execute closure
        workerLog("Executing closure...");
        $result = $closure();
        workerLog("Closure executed successfully");

        // Serialize result
        $response = [
            'ok' => true,
            'result' => $result,
            'task_id' => $taskId,
        ];

        workerLog("Task $taskId completed successfully");
    } catch (Throwable $e) {
        // Return error
        $response = [
            'ok' => false,
            'error' => $e->getMessage(),
            'task_id' => $taskId,
        ];

        workerLog("Task $taskId failed: ".$e->getMessage());
    }

    // Send response
    workerLog("Sending response for task: {$taskId}");
    $responseJson = json_encode($response);
    if ($responseJson === false) {
        workerLog("Failed to encode response for task: {$taskId}");
        continue;
    }
    $responseLength = pack('N', strlen($responseJson));

    fwrite(STDOUT, $responseLength.$responseJson);
    fflush(STDOUT);
    workerLog("Response sent for task: $taskId");
}

workerLog('Worker shutting down');
