<?php

declare(strict_types=1);

namespace Parallite\Service;

use RuntimeException;

/**
 * Service responsible for configuration management and environment detection
 */
final class ConfigService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        if ($projectRoot !== null) {
            $this->projectRoot = $projectRoot;
        } else {
            $cwd = getcwd();

            if ($cwd !== false && file_exists($cwd . '/vendor/autoload.php')) {
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
            return '\\\\.\\pipe\\parallite_' . getmypid();
        }

        return sys_get_temp_dir() . '/parallite_' . getmypid() . '.sock';
    }

    /**
     * Load daemon configuration from parallite.json
     *
     * @return array<string, mixed>
     */
    public function loadDaemonConfig(): array
    {
        $defaults = [
            'timeout_ms' => 30000,
            'fixed_workers' => 0,
            'prefix_name' => 'parallite_worker',
            'fail_mode' => 'continue',
            'max_payload_bytes' => 10485760,
        ];

        $clientConfigPath = $this->projectRoot . '/parallite.json';
        $packageRoot = dirname(__DIR__, 2);
        $packageConfigPath = $packageRoot . '/parallite.json';

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
     * Get configuration file path
     */
    public function getConfigPath(): string
    {
        $clientConfigPath = $this->projectRoot . '/parallite.json';
        $packageRoot = dirname(__DIR__, 2);
        $packageConfigPath = $packageRoot . '/parallite.json';

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
        $resolver = new BinaryResolverService();

        return $resolver->getBinaryPath();
    }
}
