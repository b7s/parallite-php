<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use const GLOB_ONLYDIR;

use RuntimeException;

use function basename;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_executable;
use function is_file;
use function is_string;
use function json_decode;
use function mkdir;
use function preg_match;
use function reset;
use function str_contains;
use function sys_get_temp_dir;
use function trim;
use function uksort;
use function unlink;
use function version_compare;

/**
 * Service responsible for resolving and caching the Parallite binary path
 */
final class BinaryResolverService
{
    private const string CACHE_FILE = 'parallite-binary-latest.cache';

    private string $parallitePhpRoot;

    /**
     * @param  string|null  $parallitePhpRoot  Parallite PHP root directory
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
            $this->ensureBinaryIsExecutable($cached);

            return $cached;
        }

        $binaryPath = $this->findLatestBinary();

        if ($binaryPath === null) {
            throw new RuntimeException(
                'Parallite binary not found. Please run: `composer update` or try `composer parallite:install` or `composer parallite:update`'
            );
        }

        $this->setCachedBinaryPath($binaryPath);

        return $binaryPath;
    }

    /**
     * Get the directory where binaries are stored
     */
    public function getBinariesDirectory(): string
    {
        return $this->parallitePhpRoot.'/bin/parallite-bin';
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

        if (! is_dir($binariesDir)) {
            return null;
        }

        $pattern = $binariesDir.'/parallite-*';

        $files = glob($pattern);

        if ($files === false || count($files) === 0) {
            return null;
        }

        $versions = [];

        foreach ($files as $file) {
            $basename = basename($file);

            $matchResult = preg_match('/parallite-(\d+\.\d+\.\d+)$/', $basename, $matches);
            if ($matchResult === 1 && is_file($file)) {
                $version = $matches[1];
                $versions[$version] = $file;
            }
        }

        if (count($versions) === 0) {
            return null;
        }

        uksort($versions, static fn ($a, $b) => version_compare($b, $a));

        $latest = reset($versions);
        if (! is_string($latest)) {
            return null;
        }

        $this->ensureBinaryIsExecutable($latest);

        return $latest;
    }

    /**
     * Ensure Unix binary has executable permission.
     */
    private function ensureBinaryIsExecutable(string $binaryPath): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        if (is_executable($binaryPath)) {
            return;
        }

        if (@chmod($binaryPath, 0755)) {
            return;
        }

        throw new RuntimeException(
            "Parallite binary exists but is not executable: {$binaryPath}. ".
            'Please verify file permissions and ownership.'
        );
    }

    /**
     * Get cached binary path if valid
     */
    private function getCachedBinaryPath(): ?string
    {
        $cacheFile = $this->getCacheFilePath();

        if (! file_exists($cacheFile)) {
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

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents($cacheFile, $path);
    }

    /**
     * Get the cache file path
     */
    private function getCacheFilePath(): string
    {
        return sys_get_temp_dir().'/'.self::CACHE_FILE;
    }

    /**
     * Find the parallite-php package root directory
     */
    private function findParallitePhpRoot(): string
    {
        $dir = __DIR__;

        $packageRoot = dirname($dir, 3);

        if (file_exists($packageRoot.'/composer.json')) {
            $composerJson = file_get_contents($packageRoot.'/composer.json');
            if ($composerJson !== false) {
                $composer = json_decode($composerJson, true);
                if (is_array($composer) && isset($composer['name']) && is_string($composer['name'])) {
                    $packageName = $composer['name'];
                    if (str_contains($packageName, '/parallite-php')) {
                        return $packageRoot;
                    }
                }
            }
        }

        $projectRoot = ProjectRootFinderService::find($dir);

        $vendorDir = $projectRoot.'/vendor';

        if (is_dir($vendorDir)) {
            $vendors = glob($vendorDir.'/*', GLOB_ONLYDIR);

            if ($vendors !== false) {
                foreach ($vendors as $vendorPath) {
                    $packagePath = $vendorPath.'/parallite-php';
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
