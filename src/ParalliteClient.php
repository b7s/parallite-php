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
 * The constructor automatically loads php_includes from parallite.json if it exists,
 * ensuring that helper files, configurations, and dependencies are available
 * in the parallel execution context.
 * 
 * Usage:
 * ```php
 * use Parallite\ParalliteClient;
 * 
 * // Option 1: Manual daemon management (you start daemon yourself)
 * $client = new ParalliteClient('/tmp/parallite-custom.sock');
 * 
 * // Option 2: Automatic daemon management (client starts/stops daemon)
 * $client = new ParalliteClient('/tmp/parallite-custom.sock', autoManageDaemon: true);
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
 * - PHP 8.3+
 * - opis/closure
 * - ext-sockets
 * 
 * Optional configuration (parallite.json in project root):
 * {
 *   "php_includes": [
 *     "config/bootstrap.php",
 *     "helpers/functions.php"
 *   ]
 * }
 */
class ParalliteClient
{
    private string $socketPath;
    private ?int $daemonPid = null;
    private bool $autoManageDaemon = false;
    private string $projectRoot;

    /**
     * Create a new Parallite client
     * 
     * Automatically loads php_includes from parallite.json if it exists.
     * 
     * @param string $socketPath Path to socket (Unix: /tmp/file.sock, Windows: \\.\pipe\name)
     * @param bool $autoManageDaemon If true, automatically starts/stops daemon
     * @param string|null $projectRoot Project root directory (auto-detected if null)
     */
    public function __construct(
        string $socketPath = '',
        bool $autoManageDaemon = false,
        ?string $projectRoot = null
    )
    {
        $this->socketPath = $socketPath !== '' ? $socketPath : self::getDefaultSocketPath();
        $this->autoManageDaemon = $autoManageDaemon;
        $this->projectRoot = $projectRoot ?? $this->findProjectRoot();

        // Load configuration and includes
        $this->loadConfiguration($this->projectRoot);

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
     * Load configuration from parallite.json and include required files
     * 
     * This method reads the parallite.json configuration file from the project root
     * and loads all files specified in the php_includes array.
     * 
     * @param string|null $projectRoot Project root directory (auto-detected if null)
     * @return void
     */
    private function loadConfiguration(?string $projectRoot): void
    {
        $projectRoot = $projectRoot ?? $this->findProjectRoot();
        $configPath = $projectRoot.'/parallite.json';

        if (!file_exists($configPath)) {
            return;
        }

        $config = json_decode(file_get_contents($configPath), true);
        
        if (!is_array($config)) {
            return;
        }

        // Load php_includes from configuration
        if (isset($config['php_includes']) && is_array($config['php_includes'])) {
            foreach ($config['php_includes'] as $include) {
                $includePath = $projectRoot.'/'.$include;
                if (file_exists($includePath)) {
                    require_once $includePath;
                }
            }
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
     * @return array Future containing socket and task_id
     * @throws RuntimeException If connection or send fails
     */
    public function async(Closure $closure): array
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (!$socket || !@socket_connect($socket, $this->socketPath)) {
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
     * @param array|null $future The future returned by async()
     * @return mixed The result of the task execution
     * @throws RuntimeException If reading fails or task failed
     */
    public function await(?array $future = null): mixed
    {
        if (empty($future) || empty($future['socket'])) {
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
     * @return array Array of results in the same order
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
     * @param mixed $socket The socket to read from
     * @return string The message data
     * @throws RuntimeException If reading fails
     */
    private function readFrame(mixed $socket): string
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
        $length = unpack('N', $lengthData)[1];

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
        $socketType = $this->isWindows() ? AF_INET : AF_UNIX;
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
    private function isWindows(): bool
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

        $configPath = $this->projectRoot.'/parallite.json';
        $logFile = sys_get_temp_dir().'/parallite_client_'.getmypid().'.log';

        if ($this->isWindows()) {
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
        $cmd = sprintf(
            '%s --config=%s --socket=%s --timeout-ms=%d --fixed-workers=%d --prefix-name=%s --fail-mode=%s %s --db-retention-minutes=%d > %s 2>&1 & echo $!',
            escapeshellarg($binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($this->socketPath),
            $config['timeout_ms'],
            $config['fixed_workers'],
            $config['prefix_name'],
            $config['fail_mode'],
            $config['db_persistent'] ? '--db-persistent' : '',
            $config['db_retention_minutes'],
            escapeshellarg($logFile)
        );

        $output = shell_exec($cmd);
        $this->daemonPid = $output !== null ? (int) trim($output) : null;

        if ($this->daemonPid === null || $this->daemonPid === 0) {
            $logContent = file_exists($logFile) ? file_get_contents($logFile) : 'Log not found';
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
        $cmd = sprintf(
            'start /B "" %s --config=%s --socket=%s --timeout-ms=%d --fixed-workers=%d --prefix-name=%s --fail-mode=%s %s --db-retention-minutes=%d > %s 2>&1',
            escapeshellarg($binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($this->socketPath),
            $config['timeout_ms'],
            $config['fixed_workers'],
            $config['prefix_name'],
            $config['fail_mode'],
            $config['db_persistent'] ? '--db-persistent' : '',
            $config['db_retention_minutes'],
            escapeshellarg($logFile)
        );

        pclose(popen($cmd, 'r'));
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
            if ($this->isWindows()) {
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
        $binaryName = $this->isWindows() ? 'parallite.exe' : 'parallite';
        
        $paths = [
            $this->projectRoot.'/vendor/bin/'.$binaryName,
            $this->projectRoot.'/bin/'.$binaryName,
        ];
        
        if (!$this->isWindows()) {
            $paths[] = '/usr/local/bin/parallite';
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                if ($this->isWindows() || is_executable($path)) {
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
     * @return array<string, mixed> Configuration array
     */
    private function loadDaemonConfig(): array
    {
        $configPath = $this->projectRoot.'/parallite.json';

        $defaults = [
            'timeout_ms' => 30000,
            'fixed_workers' => 0,
            'prefix_name' => 'parallite_worker',
            'fail_mode' => 'continue',
            'db_persistent' => false,
            'db_retention_minutes' => 60,
        ];

        if (file_exists($configPath)) {
            $json = file_get_contents($configPath);
            $config = json_decode($json, true);

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
