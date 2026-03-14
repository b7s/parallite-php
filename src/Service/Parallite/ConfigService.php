<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use RuntimeException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function fileperms;
use function getcwd;
use function getmypid;
use function is_dir;
use function is_int;
use function is_string;
use function is_writable;
use function json_decode;
use function mb_strtolower;
use function preg_match;
use function realpath;
use function str_starts_with;
use function strpos;
use function time;

/**
 * Service responsible for configuration management and environment detection
 */
final class ConfigService
{
    private string $projectRoot;

    /** @var array<string, mixed>|null */
    private ?array $cachedDaemonConfig = null;

    private ?string $cachedConfigPath = null;

    private int $configCacheTime = 0;

    private const CONFIG_CACHE_TTL = 60;

    public function __construct(?string $projectRoot = null)
    {
        if ($projectRoot !== null) {
            $this->projectRoot = $projectRoot;
        } else {
            $cwd = getcwd();

            if ($cwd !== false && file_exists($cwd.'/vendor/autoload.php')) {
                $this->projectRoot = $cwd;
            } else {
                $this->projectRoot = ProjectRootFinderService::find(dirname(__DIR__));
            }
        }
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * Check if running on Windows
     */
    public static function isWindows(): bool
    {
        return mb_strtolower(PHP_OS_FAMILY) === 'windows';
    }

    /**
     * Get default socket path for the current platform
     */
    public static function getDefaultSocketPath(): string
    {
        if (self::isWindows()) {
            return '\\\\.\\pipe\\parallite_'.getmypid();
        }

        $tempDir = sys_get_temp_dir();
        if (! is_dir($tempDir) || ! is_writable($tempDir)) {
            throw new RuntimeException('Temporary directory is not accessible');
        }

        $perms = fileperms($tempDir);
        if ($perms !== false) {
            $mode = $perms & 0777;
            if (($mode & 0002) !== 0 && ($mode & 01000) === 0) {
                syslog(LOG_INFO, 'Warning: Temporary directory is world-writable without sticky bit');
            }
        }

        return $tempDir.'/parallite_'.getmypid().'.sock';
    }

    public static function validateSocketPath(string $socketPath): void
    {
        if (self::isWindows()) {
            if (preg_match('/^\\\\\.\\\\pipe\\\\[a-zA-Z0-9_-]+$/', $socketPath) !== 1) {
                throw new RuntimeException('Invalid named pipe path format');
            }
        } else {
            $realPath = realpath(dirname($socketPath));
            if ($realPath === false) {
                throw new RuntimeException('Socket directory does not exist');
            }

            $tempDir = sys_get_temp_dir();
            if (strpos($realPath, $tempDir) !== 0) {
                throw new RuntimeException('Socket must be in temporary directory');
            }
        }
    }

    /**
     * Load daemon configuration from parallite.json
     *
     * @return array<string, mixed>
     */
    public function loadDaemonConfig(): array
    {
        $clientConfigPath = $this->projectRoot.'/parallite.json';
        $packageRoot = dirname(__DIR__, 3);
        $packageConfigPath = $packageRoot.'/parallite.json';

        $configPath = file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;

        if ($this->cachedConfigPath === $configPath &&
            $this->cachedDaemonConfig !== null &&
            (time() - $this->configCacheTime) < self::CONFIG_CACHE_TTL) {
            return $this->cachedDaemonConfig;
        }

        $defaults = [
            'timeout_ms' => 900000,
            'fixed_workers' => 1,
            'prefix_name' => 'parallite_worker',
            'fail_mode' => 'continue',
            'max_payload_bytes' => 10485760,
        ];

        if (file_exists($configPath)) {
            $json = file_get_contents($configPath);
            if ($json === false) {
                return $defaults;
            }

            $config = json_decode($json, true);
            if (! is_array($config)) {
                return $defaults;
            }

            if (isset($config['go_overrides']) && is_array($config['go_overrides'])) {
                $overrides = $config['go_overrides'];

                if (isset($overrides['timeout_ms'])) {
                    if (! is_int($overrides['timeout_ms']) || $overrides['timeout_ms'] < 1000 || $overrides['timeout_ms'] > 3600000) {
                        syslog(LOG_INFO, 'Invalid timeout_ms in config, using default');
                        unset($overrides['timeout_ms']);
                    }
                }

                if (isset($overrides['fixed_workers'])) {
                    if (! is_int($overrides['fixed_workers']) || $overrides['fixed_workers'] < 0 || $overrides['fixed_workers'] > 100) {
                        syslog(LOG_INFO, 'Invalid fixed_workers in config, using default');
                        unset($overrides['fixed_workers']);
                    }
                }

                if (isset($overrides['prefix_name'])) {
                    if (! is_string($overrides['prefix_name']) || preg_match('/^[a-zA-Z0-9_-]+$/', $overrides['prefix_name']) !== 1) {
                        syslog(LOG_INFO, 'Invalid prefix_name in config, using default');
                        unset($overrides['prefix_name']);
                    }
                }

                if (isset($overrides['fail_mode'])) {
                    $validModes = ['continue', 'stop', 'restart'];
                    if (! in_array($overrides['fail_mode'], $validModes, true)) {
                        syslog(LOG_INFO, 'Invalid fail_mode in config, using default');
                        unset($overrides['fail_mode']);
                    }
                }

                if (isset($overrides['max_payload_bytes'])) {
                    if (! is_int($overrides['max_payload_bytes']) || $overrides['max_payload_bytes'] < 1024 || $overrides['max_payload_bytes'] > 104857600) {
                        syslog(LOG_INFO, 'Invalid max_payload_bytes in config, using default');
                        unset($overrides['max_payload_bytes']);
                    }
                }

                $defaults = array_merge($defaults, $overrides);
            }
        }

        $this->cachedDaemonConfig = $defaults;
        $this->cachedConfigPath = $configPath;
        $this->configCacheTime = time();

        return $defaults;
    }

    /**
     * Get configuration file path
     */
    public function getConfigPath(): string
    {
        $clientConfigPath = $this->projectRoot.'/parallite.json';
        $packageRoot = dirname(__DIR__, 3);
        $packageConfigPath = $packageRoot.'/parallite.json';

        return file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;
    }

    /**
     * Find the Parallite binary executable
     *
     * @return string Binary full path
     *
     * @throws RuntimeException If binary not found
     */
    public function findBinary(): string
    {
        $resolver = new BinaryResolverService;

        return $resolver->getBinaryPath();
    }

    /**
     * Resolve worker script absolute path from configuration
     */
    public function getWorkerScriptPath(): string
    {
        $defaultPath = $this->projectRoot.'/src/Support/parallite-worker.php';

        $configPath = $this->getConfigPath();
        if (! file_exists($configPath)) {
            return $defaultPath;
        }

        $json = file_get_contents($configPath);
        if ($json === false) {
            return $defaultPath;
        }

        $config = json_decode($json, true);
        if (! is_array($config)) {
            return $defaultPath;
        }

        $scriptPath = $config['worker_script'] ?? null;
        if (! is_string($scriptPath) || $scriptPath === '') {
            return $defaultPath;
        }

        if (! self::isAbsolutePath($scriptPath)) {
            $scriptPath = rtrim($this->projectRoot, '/\\').'/'.ltrim($scriptPath, '/\\');
        }

        $resolvedPath = realpath($scriptPath);

        if ($resolvedPath === false) {
            syslog(LOG_INFO, "Worker script not found: {$scriptPath}, using default");

            return $defaultPath;
        }

        $packageRoot = dirname(__DIR__, 2);
        $allowedDirs = [$this->projectRoot, $packageRoot];
        $isAllowed = false;
        foreach ($allowedDirs as $allowedDir) {
            if (strpos($resolvedPath, $allowedDir) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            syslog(LOG_INFO, "Worker script outside allowed directories: {$resolvedPath}, using default");

            return $defaultPath;
        }

        if (! str_ends_with($resolvedPath, '.php')) {
            syslog(LOG_INFO, "Worker script is not a PHP file: {$resolvedPath}, using default");

            return $defaultPath;
        }

        return $resolvedPath;
    }

    /**
     * Determine if provided path is absolute
     */
    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return true;
        }

        return str_starts_with($path, 'phar://');
    }
}
