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
        $this->projectRoot = $projectRoot ?? $this->findProjectRoot();
    }

    /**
     * Find project root by looking for vendor/autoload.php
     */
    private function findProjectRoot(): string
    {
        $dir = dirname(__DIR__);
        $maxLevels = 10;
        
        for ($i = 0; $i < $maxLevels; $i++) {
            if (file_exists($dir.'/vendor/autoload.php')) {
                return $dir;
            }
            
            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break;
            }
            
            $dir = $parentDir;
        }
        
        return dirname(__DIR__, 2);
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

        return sys_get_temp_dir().'/parallite_'.getmypid().'.sock';
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

        $clientConfigPath = $this->projectRoot.'/parallite.json';
        $packageRoot = dirname(__DIR__, 2);
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
     * Get configuration file path
     */
    public function getConfigPath(): string
    {
        $clientConfigPath = $this->projectRoot.'/parallite.json';
        $packageRoot = dirname(__DIR__, 2);
        $packageConfigPath = $packageRoot.'/parallite.json';
        
        return file_exists($clientConfigPath) ? $clientConfigPath : $packageConfigPath;
    }

    /**
     * Find the Parallite binary executable
     * 
     * @throws RuntimeException If binary not found
     */
    public function findBinary(): string
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
}
