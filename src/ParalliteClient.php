<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use RuntimeException;

/**
 * Parallite Client - Standalone PHP Client for Parallite Daemon
 * 
 * This class provides a simple interface to communicate with Parallite daemon
 * and execute PHP closures in parallel.
 * 
 * Usage:
 * ```php
 * use Parallite\ParalliteClient;
 * 
 * // Option 1: Automatic daemon management (recommended)
 * $client = new ParalliteClient(autoManageDaemon: true);
 * 
 * // Option 2: Manual daemon management (you start daemon yourself)
 * $client = new ParalliteClient('/tmp/parallite-custom.sock', autoManageDaemon: false);
 * 
 * // Submit tasks
 * $future1 = $client->async(fn() => sleep(1) && 'Task 1');
 * $future2 = $client->async(fn() => sleep(2) && 'Task 2');
 * 
 * // Await results
 * $result1 = $client->await($future1);
 * $result2 = $client->await($future2);
 * 
 * // Daemon is automatically stopped on script end if autoManageDaemon=true
 * ```
 * 
 * Required dependencies:
 * - PHP 8.2+
 * - opis/closure
 * - ext-sockets
 * 
 * Configuration (parallite.json in project root):
 * - php_includes: Files loaded by worker processes
 * - go_overrides: Daemon configuration (timeout, workers, etc)
 */
class ParalliteClient
{
    private string $socketPath;
    private ?int $daemonPid = null;
    private bool $autoManageDaemon = true;
    private string $projectRoot;

    /**
     * Create a new Parallite client
     * 
     * @param string $socketPath Path to socket (Unix: /tmp/file.sock, Windows: \\.\pipe\name)
     * @param bool $autoManageDaemon If true, automatically starts/stops daemon
     * @param string|null $projectRoot Project root directory (auto-detected if null)
     */
    public function __construct(
        string $socketPath = '',
        bool $autoManageDaemon = true,
        ?string $projectRoot = null
    )
    {
        $this->socketPath = $socketPath !== '' ? $socketPath : self::getDefaultSocketPath();
        $this->autoManageDaemon = $autoManageDaemon;
        $this->projectRoot = $projectRoot ?? $this->findProjectRoot();

        // Auto-start daemon if enabled
        if ($this->autoManageDaemon) {
            $this->ensureDaemonRunning();
        }

        // Register shutdown handler to stop daemon
        if ($this->autoManageDaemon) {
            register_shutdown_function([$this, 'stopDaemon']);
        }
    }

    /**
     * Find project root by looking for vendor/autoload.php
     * 
     * @return string Project root directory
     */
    private function findProjectRoot(): string
    {
        $dir = __DIR__;
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
        
        // Fallback to parent directory
        return dirname(__DIR__);
    }

    /**
     * Submit a task for parallel execution
     * 
     * This method sends the task to Parallite daemon and returns a future
     * that can be awaited later. The socket is kept open to allow parallel execution.
     * 
     * @param Closure $closure The closure to execute
     * @return array{socket: \Socket, task_id: string} Future containing socket and task_id
     * @throws RuntimeException If connection or send fails
     */
    public function async(Closure $closure): array
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if ($socket === false) {
            throw new RuntimeException('Failed to create socket');
        }
        
        if (!@socket_connect($socket, $this->socketPath)) {
            throw new RuntimeException('Failed to connect to daemon at: ' . $this->socketPath);
        }

        // Generate random task ID (UUID v4 format)
        $taskId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // Serialize the closure
        $serialized = \Opis\Closure\serialize($closure);

        // Prepare the submit message
        $message = json_encode([
            'action' => 'submit',
            'task_id' => $taskId,
            'payload' => base64_encode($serialized),
            'context' => [],
        ]);
        
        if ($message === false) {
            throw new RuntimeException('Failed to encode message');
        }

        // Pack message with 4-byte length prefix (big-endian)
        $length = pack('N', strlen($message));
        $fullMessage = $length . $message;

        // Send the complete frame to the daemon
        $bytesSent = socket_write($socket, $fullMessage, strlen($fullMessage));

        if ($bytesSent === false) {
            socket_close($socket);
            throw new RuntimeException('Failed to send message');
        }

        // Return socket and task ID WITHOUT reading result
        // This allows multiple tasks to execute in parallel!
        return ['socket' => $socket, 'task_id' => $taskId];
    }

    /**
     * Await the result of a previously submitted task
     * 
     * This method reads the response from the open socket and returns the result.
     * The socket is automatically closed after reading.
     * 
     * @param array{socket: \Socket|null, task_id: string}|null $future The future returned by async()
     * @return mixed The result of the task execution
     * @throws RuntimeException If reading fails or task failed
     */
    public function await(?array $future = null): mixed
    {
        if (!is_array($future) || !isset($future['socket'])) {
            throw new RuntimeException('No future or socket provided');
        }

        $socket = $future['socket'];

        try {
            // Read the response frame from the daemon
            $response = $this->readFrame($socket);
        } finally {
            // Always close the socket when done
            socket_close($socket);
        }

        // Parse the JSON response
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid response from daemon');
        }

        // Check if the task succeeded
        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new RuntimeException('Task failed: ' . ($data['error'] ?? 'unknown error'));
        }

        // Return the result (already deserialized by the worker)
        return $data['result'] ?? null;
    }

    /**
     * Submit multiple tasks and await all results
     * 
     * This is a convenience method that combines async() and await()
     * for multiple tasks, similar to Promise.all() in JavaScript.
     * 
     * @param array<Closure> $closures Array of closures to execute
     * @return array<mixed> Array of results in the same order
     */
    public function awaitAll(array $closures): array
    {
        // Submit all tasks
        $futures = [];
        foreach ($closures as $closure) {
            $futures[] = $this->async($closure);
        }

        // Await all results
        $results = [];
        foreach ($futures as $future) {
            $results[] = $this->await($future);
        }

        return $results;
    }

    /**
     * Read a frame from socket (4-byte big-endian length + data)
     * 
     * Parallite uses a simple framing protocol:
     * 1. First 4 bytes: message length (big-endian unsigned int)
     * 2. Next N bytes: JSON message data
     * 
     * We read in chunks to handle partial reads correctly.
     * 
     * @param \Socket $socket The socket to read from
     * @return string The message data
     * @throws RuntimeException If reading fails
     */
    private function readFrame(\Socket $socket): string
    {
        // Step 1: Read the 4-byte length prefix
        $lengthData = '';
        $remaining = 4;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read length: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF');
            }

            $lengthData .= $chunk;
            $remaining -= strlen($chunk);
        }

        // Unpack the length (N = unsigned long, big-endian, 32-bit)
        $unpacked = unpack('N', $lengthData);
        if ($unpacked === false) {
            throw new RuntimeException('Failed to unpack length');
        }
        $length = $unpacked[1];

        // Step 2: Read the actual message data
        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read data: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Ensure the daemon is running, start it if not
     * 
     * @return void
     * @throws RuntimeException If daemon fails to start
     */
    private function ensureDaemonRunning(): void
    {
        if ($this->isDaemonRunning()) {
            return;
        }

        $this->startDaemon();
    }

    /**
     * Check if daemon is running
     * 
     * @return bool True if daemon is running
     */
    private function isDaemonRunning(): bool
    {
        // Check if socket exists and is accessible
        if (!file_exists($this->socketPath)) {
            return false;
        }

        // Try to connect to verify daemon is responsive
        $socketType = self::isWindows() ? AF_INET : AF_UNIX;
        $socket = @socket_create($socketType, SOCK_STREAM, 0);
        if ($socket === false) {
            return false;
        }

        $connected = @socket_connect($socket, $this->socketPath);
        @socket_close($socket);

        return $connected !== false;
    }

    /**
     * Check if running on Windows
     * 
     * @return bool True if Windows
     */
    private static function isWindows(): bool
    {
        return mb_strtolower(PHP_OS_FAMILY) === 'windows';
    }

    /**
     * Get default socket path for the current platform
     * 
     * @return string Socket path (Unix socket or Windows named pipe)
     */
    public static function getDefaultSocketPath(): string
    {
        if (self::isWindows()) {
            // Windows: Named Pipe format
            return '\\\\.\\pipe\\parallite_'.getmypid();
        }

        // Unix/Linux/macOS: Unix socket
        return sys_get_temp_dir().'/parallite_'.getmypid().'.sock';
    }

    /**
     * Start the Parallite daemon
     * 
     * @return void
     * @throws RuntimeException If daemon fails to start
     */
    private function startDaemon(): void
    {
        $binaryPath = $this->findBinary();
        $config = $this->loadDaemonConfig();

        // Remove stale socket
        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }

        // Determine config path (client project or package)
        $clientConfigPath = $this->projectRoot.'/parallite.json';
        $packageConfigPath = dirname(__DIR__).'/parallite.json';
        $configPath = file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;
        
        $logFile = sys_get_temp_dir().'/parallite_client_'.getmypid().'.log';

        if (self::isWindows()) {
            $this->startDaemonWindows($binaryPath, $configPath, $config, $logFile);
        } else {
            $this->startDaemonUnix($binaryPath, $configPath, $config, $logFile);
        }

        // Wait for socket to be ready
        $this->waitForSocket($logFile);
    }

    /**
     * Start daemon on Unix-like systems (Linux/macOS)
     * 
     * @param string $binaryPath Path to parallite binary
     * @param string $configPath Path to parallite.json
     * @param array<string, mixed> $config Configuration array
     * @param string $logFile Path to log file
     * @return void
     * @throws RuntimeException If daemon fails to start
     */
    private function startDaemonUnix(string $binaryPath, string $configPath, array $config, string $logFile): void
    {
        $timeoutMs = is_int($config['timeout_ms'] ?? null) ? $config['timeout_ms'] : 30000;
        $fixedWorkers = is_int($config['fixed_workers'] ?? null) ? $config['fixed_workers'] : 0;
        $prefixName = is_string($config['prefix_name'] ?? null) ? $config['prefix_name'] : 'parallite_worker';
        $failMode = is_string($config['fail_mode'] ?? null) ? $config['fail_mode'] : 'continue';
        
        $cmd = sprintf(
            '%s --config=%s --socket=%s --timeout-ms=%d --fixed-workers=%d --prefix-name=%s --fail-mode=%s > %s 2>&1 & echo $!',
            escapeshellarg($binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($this->socketPath),
            $timeoutMs,
            $fixedWorkers,
            $prefixName,
            $failMode,
            escapeshellarg($logFile)
        );

        $output = shell_exec($cmd);
        if ($output === null || $output === false) {
            throw new RuntimeException('Failed to execute daemon start command');
        }
        $this->daemonPid = (int) trim($output);

        if ($this->daemonPid === 0) {
            $logContent = 'Log not found';
            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                $logContent = $content !== false ? $content : 'Failed to read log';
            }
            throw new RuntimeException(
                "Failed to start Parallite daemon. Log: {$logFile}\n{$logContent}"
            );
        }
    }

    /**
     * Start daemon on Windows
     * 
     * @param string $binaryPath Path to parallite binary
     * @param string $configPath Path to parallite.json
     * @param array<string, mixed> $config Configuration array
     * @param string $logFile Path to log file
     * @return void
     */
    private function startDaemonWindows(string $binaryPath, string $configPath, array $config, string $logFile): void
    {
        $timeoutMs = is_int($config['timeout_ms'] ?? null) ? $config['timeout_ms'] : 30000;
        $fixedWorkers = is_int($config['fixed_workers'] ?? null) ? $config['fixed_workers'] : 0;
        $prefixName = is_string($config['prefix_name'] ?? null) ? $config['prefix_name'] : 'parallite_worker';
        $failMode = is_string($config['fail_mode'] ?? null) ? $config['fail_mode'] : 'continue';
        
        $cmd = sprintf(
            'start /B "" %s --config=%s --socket=%s --timeout-ms=%d --fixed-workers=%d --prefix-name=%s --fail-mode=%s > %s 2>&1',
            escapeshellarg($binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($this->socketPath),
            $timeoutMs,
            $fixedWorkers,
            $prefixName,
            $failMode,
            escapeshellarg($logFile)
        );

        $handle = popen($cmd, 'r');
        if ($handle !== false) {
            pclose($handle);
        }
        $this->daemonPid = -1;
    }

    /**
     * Stop the daemon process
     * 
     * @return void
     */
    public function stopDaemon(): void
    {
        if ($this->daemonPid !== null) {
            if (self::isWindows()) {
                if (file_exists($this->socketPath)) {
                    @unlink($this->socketPath);
                }
            } else {
                if (function_exists('posix_kill') && posix_kill($this->daemonPid, 0)) {
                    posix_kill($this->daemonPid, SIGTERM);
                }
                
                if (file_exists($this->socketPath)) {
                    @unlink($this->socketPath);
                }
            }
            
            $this->daemonPid = null;
        }
    }

    /**
     * Find the Parallite binary executable
     * 
     * @return string Path to binary
     * @throws RuntimeException If binary not found
     */
    private function findBinary(): string
    {
        $binaryName = self::isWindows() ? 'parallite.exe' : 'parallite';
        
        $paths = [
            $this->projectRoot.'/vendor/bin/'.$binaryName,
            $this->projectRoot.'/bin/'.$binaryName,
        ];
        
        if (!self::isWindows()) {
            $paths[] = '/usr/local/bin/parallite';
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                if (self::isWindows() || is_executable($path)) {
                    return $path;
                }
            }
        }

        throw new RuntimeException(
            'Parallite binary not found. Please run: composer parallite:install'
        );
    }

    /**
     * Load daemon configuration from parallite.json
     * 
     * Priority:
     * 1. Client project root (where composer.json is)
     * 2. Package root (parallite-php package)
     * 
     * @return array<string, mixed> Configuration array
     */
    private function loadDaemonConfig(): array
    {
        $defaults = [
            'timeout_ms' => 30000,
            'fixed_workers' => 0,
            'prefix_name' => 'parallite_worker',
            'fail_mode' => 'continue',
            'max_payload_bytes' => 10485760,
        ];

        // Try client project root first
        $clientConfigPath = $this->projectRoot.'/parallite.json';
        
        // If not found, try package root
        $packageRoot = dirname(__DIR__);
        $packageConfigPath = $packageRoot.'/parallite.json';
        
        $configPath = file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;

        if (file_exists($configPath)) {
            $json = file_get_contents($configPath);
            if ($json === false) {
                return $defaults;
            }
            
            $config = json_decode($json, true);
            if (!is_array($config)) {
                return $defaults;
            }

            if (isset($config['go_overrides']) && is_array($config['go_overrides'])) {
                $defaults = array_merge($defaults, $config['go_overrides']);
            }
        }

        return $defaults;
    }

    /**
     * Wait for daemon socket to be ready
     * 
     * @param string $logFile Path to daemon log file
     * @param int $maxAttempts Maximum number of attempts
     * @param int $sleepMs Milliseconds to sleep between attempts
     * @return void
     * @throws RuntimeException If socket not ready after max attempts
     */
    private function waitForSocket(string $logFile = '', int $maxAttempts = 50, int $sleepMs = 100): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (file_exists($this->socketPath)) {
                usleep($sleepMs * 1000);
                return;
            }

            usleep($sleepMs * 1000);
        }

        $errorMsg = 'Parallite daemon failed to start within timeout.';
        if ($logFile !== '' && file_exists($logFile)) {
            $errorMsg .= " Check log: {$logFile}";
        }

        throw new RuntimeException($errorMsg);
    }
}
