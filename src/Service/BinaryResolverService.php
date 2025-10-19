<?php

declare(strict_types=1);

namespace Parallite\Service;

use RuntimeException;

/**
 * Service responsible for resolving and caching the Parallite binary path
 */
final class BinaryResolverService
{
    private const string CACHE_FILE = 'parallite-binary-latest.cache';

    private string $parallitePhpRoot;

    /**
     * @param string|null $parallitePhpRoot Parallite PHP root directory
     */
    public function __construct(?string $parallitePhpRoot = null)
    {
        if ($parallitePhpRoot !== null) {
            $this->parallitePhpRoot = $parallitePhpRoot;
        } else {
            $this->parallitePhpRoot = $this->findParallitePhpRoot();
        }
    }

    /**
     * Get the path to the latest Parallite binary
     * Uses cache for performance
     *
     * @throws RuntimeException If binary not found
     */
    public function getBinaryPath(): string
    {
        $cached = $this->getCachedBinaryPath();

        if ($cached !== null && file_exists($cached)) {
            return $cached;
        }

        // Cache miss or invalid - find latest binary
        $binaryPath = $this->findLatestBinary();

        if ($binaryPath === null) {
            throw new RuntimeException(
                'Parallite binary not found. Please run: `composer update` or try `composer parallite:install` or `composer parallite:update`'
            );
        }

        // Update cache
        $this->setCachedBinaryPath($binaryPath);

        return $binaryPath;
    }

    /**
     * Get the directory where binaries are stored
     */
    public function getBinariesDirectory(): string
    {
        return $this->parallitePhpRoot . '/bin/parallite-bin';
    }

    /**
     * Clear the binary path cache
     * Should be called after installing/updating binaries
     */
    public function clearCache(): void
    {
        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * Find the latest binary in the binaries directory
     */
    private function findLatestBinary(): ?string
    {
        $binariesDir = $this->getBinariesDirectory();

        if (!is_dir($binariesDir)) {
            return null;
        }

        $pattern = $binariesDir . '/parallite-*';

        $files = glob($pattern);

        if ($files === false || count($files) === 0) {
            return null;
        }

        // Extract versions and find the latest
        $versions = [];

        foreach ($files as $file) {
            $basename = basename($file);

            // Extract version from filename: parallite-1.2.3
            $matchResult = preg_match('/parallite-(\d+\.\d+\.\d+)$/', $basename, $matches);
            if ($matchResult === 1 && is_file($file)) {
                $version = $matches[1];
                $versions[$version] = $file;
            }
        }

        if (count($versions) === 0) {
            return null;
        }

        // Sort versions using version_compare
        uksort($versions, function ($a, $b) {
            return version_compare($b, $a); // Descending order
        });

        // Return the latest version
        return reset($versions);
    }

    /**
     * Get cached binary path if valid
     */
    private function getCachedBinaryPath(): ?string
    {
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cached = file_get_contents($cacheFile);

        if ($cached === false) {
            return null;
        }

        $cached = trim($cached);

        if ($cached === '') {
            return null;
        }

        return $cached;
    }

    /**
     * Cache the binary path
     */
    private function setCachedBinaryPath(string $path): void
    {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents($cacheFile, $path);
    }

    /**
     * Get the cache file path
     */
    private function getCacheFilePath(): string
    {
        return sys_get_temp_dir() . '/' . self::CACHE_FILE;
    }

    /**
     * Find the parallite-php package root directory
     */
    private function findParallitePhpRoot(): string
    {
        // Start from this file's directory (src/Service)
        $dir = __DIR__;

        // Go up to package root (2 levels: Service -> src -> root)
        $packageRoot = dirname($dir, 2);

        // Check if we're in the package itself (development or standalone)
        if (file_exists($packageRoot . '/composer.json')) {
            $composerJson = file_get_contents($packageRoot . '/composer.json');
            if ($composerJson !== false) {
                $composer = json_decode($composerJson, true);
                if (is_array($composer) && isset($composer['name']) && is_string($composer['name'])) {
                    $packageName = $composer['name'];
                    // Check if it's the parallite-php package (any vendor namespace)
                    if (str_contains($packageName, '/parallite-php')) {
                        return $packageRoot;
                    }
                }
            }
        }

        // We're installed as a dependency - find in vendor
        $projectRoot = ProjectRootFinderService::find($dir);

        // Search for parallite-php in vendor directory
        $vendorDir = $projectRoot . '/vendor';

        if (is_dir($vendorDir)) {
            // Scan vendor directory for parallite-php
            $vendors = glob($vendorDir . '/*', GLOB_ONLYDIR);

            if ($vendors !== false) {
                foreach ($vendors as $vendorPath) {
                    $packagePath = $vendorPath . '/parallite-php';
                    if (is_dir($packagePath)) {
                        return $packagePath;
                    }
                }
            }
        }

        // Fallback to package root
        return $packageRoot;
    }

}
