<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

// Test binary exists and is executable
$binaryPath = __DIR__.'/vendor/bin/parallite';

echo "Checking binary...\n";
echo "Binary path: {$binaryPath}\n";
echo "Exists: ".(file_exists($binaryPath) ? 'YES' : 'NO')."\n";
echo "Executable: ".(is_executable($binaryPath) ? 'YES' : 'NO')."\n";

if (file_exists($binaryPath)) {
    echo "\nTesting binary version:\n";
    $output = shell_exec(escapeshellarg($binaryPath).' --version 2>&1');
    echo $output."\n";
    
    echo "\nTesting binary help:\n";
    $output = shell_exec(escapeshellarg($binaryPath).' --help 2>&1');
    echo $output."\n";
}

// Check if parallite.json exists
$configPath = __DIR__.'/parallite.json';
echo "\nConfig file: {$configPath}\n";
echo "Exists: ".(file_exists($configPath) ? 'YES' : 'NO')."\n";

// Try to start daemon manually
$socketPath = '/tmp/parallite-test.sock';
$logFile = '/tmp/parallite-test.log';

if (file_exists($socketPath)) {
    unlink($socketPath);
}

echo "\nStarting daemon manually...\n";
echo "Socket: {$socketPath}\n";
echo "Log: {$logFile}\n";

$cmd = sprintf(
    '%s --socket=%s > %s 2>&1 & echo $!',
    escapeshellarg($binaryPath),
    escapeshellarg($socketPath),
    escapeshellarg($logFile)
);

echo "Command: {$cmd}\n\n";

$pid = shell_exec($cmd);
echo "PID: ".trim($pid)."\n";

sleep(2);

if (file_exists($logFile)) {
    echo "\nLog contents:\n";
    echo file_get_contents($logFile)."\n";
}

if (file_exists($socketPath)) {
    echo "\n✓ Socket created successfully!\n";
} else {
    echo "\n✗ Socket NOT created\n";
}

// Cleanup
if ($pid) {
    posix_kill((int)trim($pid), SIGTERM);
}
if (file_exists($socketPath)) {
    unlink($socketPath);
}
