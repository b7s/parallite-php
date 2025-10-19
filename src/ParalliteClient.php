<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use Parallite\Service\ConfigService;
use Parallite\Service\DaemonService;
use Parallite\Service\SocketService;
use Parallite\Service\TaskService;
use RuntimeException;
use Socket;
use Throwable;

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
    private bool $autoManageDaemon = true;
    private bool $enableBenchmark = false;
    
    private ConfigService $configService;
    private DaemonService $daemonService;
    private SocketService $socketService;
    private TaskService $taskService;

    /**
     * Create a new Parallite client
     * 
     * @param string $socketPath Path to socket (Unix: /tmp/file.sock, Windows: \\.\pipe\name)
     * @param bool $autoManageDaemon If true, automatically starts/stops daemon
     * @param string|null $projectRoot Project root directory (auto-detected if null)
     * @param bool $enableBenchmark If true, includes benchmark data in responses
     */
    public function __construct(
        string $socketPath = '',
        bool $autoManageDaemon = true,
        ?string $projectRoot = null,
        bool $enableBenchmark = false
    )
    {
        $this->socketPath = $socketPath !== '' ? $socketPath : ConfigService::getDefaultSocketPath();
        $this->autoManageDaemon = $autoManageDaemon;
        $this->enableBenchmark = $enableBenchmark;

        $this->configService = new ConfigService($projectRoot);
        $this->daemonService = new DaemonService($this->socketPath, $this->configService);
        $this->socketService = new SocketService($this->socketPath, $this->enableBenchmark);
        $this->taskService = new TaskService($this->socketService);

        if ($this->autoManageDaemon) {
            $this->daemonService->ensureDaemonRunning();
            register_shutdown_function([$this, 'stopDaemon']);
        }
    }

    /**
     * Create a Promise for chainable async execution
     * 
     * @template TReturn
     * @param Closure(): TReturn $closure The closure to execute
     * @return Promise<TReturn> Promise that supports then/catch/finally chaining
     */
    public function promise(Closure $closure): Promise
    {
        return new Promise($this, $closure);
    }

    /**
     * Submit a task for parallel execution
     * 
     * This method sends the task to Parallite daemon and returns a future
     * that can be awaited later. The socket is kept open to allow parallel execution.
     * 
     * @param Closure $closure The closure to execute
     * @return array{socket: Socket, task_id: string} Future containing socket and task_id
     * @throws RuntimeException If connection or send fails
     */
    public function async(Closure $closure): array
    {
        return $this->socketService->submitTask($closure);
    }

    /**
     * Await the result of a previously submitted task
     * 
     * This method reads the response from the open socket and returns the result.
     * The socket is automatically closed after reading.
     * If the future parameter is passed by reference, benchmark data will be stored in it.
     * 
     * @template TReturn
     * @param array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>}|Promise<TReturn>|null $future The future returned by async() or a Promise
     * @param-out array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>}|Promise<TReturn>|null $future
     * @return mixed The result of the task execution
     * @throws RuntimeException|Throwable If reading fails or task failed
     */
    public function await(array|Promise|null &$future = null): mixed
    {
        if ($future instanceof Promise) {
            return $future->resolve();
        }

        if (!is_array($future) || !isset($future['socket'])) {
            throw new RuntimeException('No future or socket provided');
        }

        return $this->socketService->awaitTask($future);
    }
    
    /**
     * Enable benchmark mode
     * 
     * When enabled, task responses will include benchmark data with:
     * - execution_time_ms: Task execution time in milliseconds
     * - memory_delta_mb: Memory change during task execution (MB)
     * - memory_peak_mb: Peak memory usage during task (MB)
     * - cpu_time_ms: Total CPU time (user + system) in milliseconds
     * 
     * @return self
     */
    public function enableBenchmark(): self
    {
        $this->enableBenchmark = true;
        $this->socketService = new SocketService($this->socketPath, $this->enableBenchmark);
        $this->taskService = new TaskService($this->socketService);
        return $this;
    }
    
    /**
     * Disable benchmark mode
     * 
     * @return self
     */
    public function disableBenchmark(): self
    {
        $this->enableBenchmark = false;
        $this->socketService = new SocketService($this->socketPath, $this->enableBenchmark);
        $this->taskService = new TaskService($this->socketService);
        return $this;
    }
    
    /**
     * Check if benchmark mode is enabled
     * 
     * @return bool
     */
    public function isBenchmarkEnabled(): bool
    {
        return $this->enableBenchmark;
    }

    /**
     * Await multiple closures in parallel
     * 
     * This is a convenience method that combines async() and await()
     * for multiple tasks, similar to Promise.all() in JavaScript.
     * 
     * @param array<Closure> $closures Array of closures to execute
     * @return array<mixed> Array of results in the same order
     */
    public function awaitAll(array $closures): array
    {
        return $this->taskService->awaitAll($closures);
    }

    /**
     * Await multiple promises/futures in parallel
     *
     * Accepts an array of Promise objects or futures and returns their results.
     * Non-promise values in the array pass through unchanged.
     *
     * @param array<Promise|array{socket: Socket|null, task_id: string}|mixed> $promises Array of promises, futures, or mixed values
     * @return array<mixed> Array of results (promises resolved, other values pass through)
     * @throws Throwable
     */
    public function awaitMultiple(array $promises): array
    {
        return $this->taskService->awaitMultiple($promises);
    }

    /**
     * Get default socket path for the current platform
     * 
     * @return string Socket path (Unix socket or Windows named pipe)
     */
    public static function getDefaultSocketPath(): string
    {
        return ConfigService::getDefaultSocketPath();
    }

    /**
     * Stop the daemon process
     */
    public function stopDaemon(): void
    {
        $this->daemonService->stopDaemon();
    }
}
