<?php

declare(strict_types=1);

namespace Parallite\Service;

use RuntimeException;

/**
 * Service responsible for managing the Parallite daemon lifecycle
 */
final class DaemonService
{
    private ?int $daemonPid = null;

    public function __construct(
        private readonly string        $socketPath,
        private readonly ConfigService $configService
    )
    {
    }

    /**
     * Check if daemon is running
     */
    public function isDaemonRunning(): bool
    {
        if (!file_exists($this->socketPath)) {
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

        // Log the binary version being used
        $version = $this->getBinaryVersion($binaryPath);
        error_log("Starting Parallite daemon (version: {$version})");

        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }

        $configPath = $this->configService->getConfigPath();
        $logFile = sys_get_temp_dir() . '/parallite_client_' . getmypid() . '.log';

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
     * @param array<string, mixed> $config
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
        $this->daemonPid = (int)trim($output);

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
     * @param array<string, mixed> $config
     */
    private function startDaemonWindows(string $binaryPath, string $configPath, array $config, string $logFile): void
    {
        [
            'timeoutMs' => $timeoutMs,
            'fixedWorkers' => $fixedWorkers,
            'prefixName' => $prefixName,
            'failMode' => $failMode
        ] = $this->extractDaemonConfig($config);

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
                }

                if (file_exists($this->socketPath)) {
                    @unlink($this->socketPath);
                }
            }

            $this->daemonPid = null;
        }
    }

    /**
     * Wait for daemon socket to be ready
     *
     * @throws RuntimeException
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

    /**
     * Extract version from binary path (parallite-1.2.3)
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
     * Extract, validate and transform daemon configuration with defaults
     *
     * @param array<string, mixed> $config
     * @return array{timeoutMs: int, fixedWorkers: int, prefixName: string, failMode: string}
     */
    private function extractDaemonConfig(array $config): array
    {
        return [
            'timeoutMs' => is_int($config['timeout_ms'] ?? null) ? $config['timeout_ms'] : 30000,
            'fixedWorkers' => is_int($config['fixed_workers'] ?? null) ? $config['fixed_workers'] : 0,
            'prefixName' => is_string($config['prefix_name'] ?? null) ? $config['prefix_name'] : 'parallite_worker',
            'failMode' => is_string($config['fail_mode'] ?? null) ? $config['fail_mode'] : 'continue',
        ];
    }
}
