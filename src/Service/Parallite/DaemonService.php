<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use RuntimeException;

/**
 * Service responsible for managing the Parallite daemon lifecycle
 */
final class DaemonService
{
    private ?int $daemonPid = null;

    public function __construct(
        private readonly string $socketPath,
        private readonly ConfigService $configService
    ) {}

    /**
     * Check if the daemon is running
     */
    public function isDaemonRunning(): bool
    {
        if (! file_exists($this->socketPath)) {
            return false;
        }

        $socketType = ConfigService::isWindows() ? AF_INET : AF_UNIX;
        $socket = @socket_create($socketType, SOCK_STREAM, 0);
        if ($socket === false) {
            return false;
        }

        $connected = @socket_connect($socket, $this->socketPath);
        @socket_close($socket);

        return $connected !== false;
    }

    /**
     * Ensure the daemon is running, start it if not
     *
     * @throws RuntimeException
     */
    public function ensureDaemonRunning(): void
    {
        if ($this->isDaemonRunning()) {
            return;
        }

        $this->startDaemon();
    }

    /**
     * Start the Parallite daemon
     *
     * @throws RuntimeException
     */
    public function startDaemon(): void
    {
        $binaryPath = $this->configService->findBinary();
        $config = $this->configService->loadDaemonConfig();

        $version = $this->getBinaryVersion($binaryPath);
        error_log("Starting Parallite daemon (version: {$version})");

        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }

        $configPath = $this->configService->getConfigPath();
        $logFile = tempnam(sys_get_temp_dir(), 'parallite_client_');
        if ($logFile === false) {
            throw new RuntimeException('Failed to create temporary log file');
        }
        $logFile .= '.log';
        rename(substr($logFile, 0, -4), $logFile);
        chmod($logFile, 0600);

        if (ConfigService::isWindows()) {
            $this->startDaemonWindows($binaryPath, $configPath, $config, $logFile);
        } else {
            $this->startDaemonUnix($binaryPath, $configPath, $config, $logFile);
        }

        $this->waitForSocket($logFile);
    }

    /**
     * Start daemon on Unix-like systems (Linux/macOS)
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    private function startDaemonUnix(string $binaryPath, string $configPath, array $config, string $logFile): void
    {
        [
            'timeoutMs' => $timeoutMs,
            'fixedWorkers' => $fixedWorkers,
            'prefixName' => $prefixName,
            'failMode' => $failMode
        ] = $this->extractDaemonConfig($config);

        $workerScript = $this->configService->getWorkerScriptPath();

        $descriptorspec = [
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $command = [
            $binaryPath,
            '--config', $configPath,
            '--socket', $this->socketPath,
            '--timeout-ms', (string) $timeoutMs,
            '--fixed-workers', (string) $fixedWorkers,
            '--prefix-name', $prefixName,
            '--fail-mode', $failMode,
            '--worker-script', $workerScript,
        ];

        $process = proc_open(
            $command,
            $descriptorspec,
            $pipes,
            null
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start daemon process');
        }

        $status = proc_get_status($process);

        $this->daemonPid = $status['pid'];

        if ($this->daemonPid === 0) {
            proc_terminate($process);
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
     * @param  array<string, mixed>  $config
     */
    private function startDaemonWindows(string $binaryPath, string $configPath, array $config, string $logFile): void
    {
        [
            'timeoutMs' => $timeoutMs,
            'fixedWorkers' => $fixedWorkers,
            'prefixName' => $prefixName,
            'failMode' => $failMode
        ] = $this->extractDaemonConfig($config);

        $workerScript = $this->configService->getWorkerScriptPath();

        $cmd = sprintf(
            'start /B "" %s --config=%s --socket=%s --timeout-ms=%d --fixed-workers=%d --prefix-name=%s --fail-mode=%s --worker-script=%s > %s 2>&1',
            escapeshellarg($binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($this->socketPath),
            $timeoutMs,
            $fixedWorkers,
            $prefixName,
            $failMode,
            escapeshellarg($workerScript),
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
     */
    public function stopDaemon(): void
    {
        if ($this->daemonPid !== null) {
            if (ConfigService::isWindows()) {
                if (file_exists($this->socketPath)) {
                    @unlink($this->socketPath);
                }
            } else {
                if (function_exists('posix_kill') && posix_kill($this->daemonPid, 0)) {
                    posix_kill($this->daemonPid, SIGTERM);

                    $timeout = 5;
                    $start = time();
                    while (posix_kill($this->daemonPid, 0)) {
                        if (time() - $start > $timeout) {
                            posix_kill($this->daemonPid, SIGKILL);
                            break;
                        }
                        usleep(100000);
                    }
                }

                if (file_exists($this->socketPath)) {
                    @unlink($this->socketPath);
                }
            }

            $this->daemonPid = null;
        }
    }

    /**
     * Wait for the daemon socket to be ready
     *
     * @throws RuntimeException
     */
    private function waitForSocket(string $logFile = '', int $maxAttempts = 50, int $sleepMs = 100): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            usleep($sleepMs * 1000);
            if (file_exists($this->socketPath)) {

                return;
            }
        }

        $errorMsg = 'Parallite daemon failed to start within timeout.';
        if ($logFile !== '' && file_exists($logFile)) {
            $errorMsg .= " Check log: {$logFile}";
        }

        throw new RuntimeException($errorMsg);
    }

    /**
     * Extract version from a binary path (parallite-1.2.3)
     */
    private function getBinaryVersion(string $binaryPath): string
    {
        $basename = basename($binaryPath);
        if (preg_match('/parallite-(\d+\.\d+\.\d+)/', $basename, $matches) === 1) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * Extract, validate, and transform daemon configuration with defaults
     *
     * @param  array<string, mixed>  $config
     * @return array{timeoutMs: int, fixedWorkers: int, prefixName: string, failMode: string}
     */
    private function extractDaemonConfig(array $config): array
    {
        return [
            'timeoutMs' => is_int($config['timeout_ms'] ?? null) ? $config['timeout_ms'] : 900000,
            'fixedWorkers' => is_int($config['fixed_workers'] ?? null) ? $config['fixed_workers'] : 1,
            'prefixName' => is_string($config['prefix_name'] ?? null) ? $config['prefix_name'] : 'parallite_worker',
            'failMode' => is_string($config['fail_mode'] ?? null) ? $config['fail_mode'] : 'continue',
        ];
    }
}
