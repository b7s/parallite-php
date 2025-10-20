<?php

declare(strict_types=1);

namespace Parallite\Service\Install;

use Parallite\Service\Parallite\BinaryResolverService;
use RuntimeException;

final class VersionService
{
    private BinaryResolverService $binaryResolver;

    public function __construct(BinaryResolverService $binaryResolver)
    {
        $this->binaryResolver = $binaryResolver;
    }

    public function getInstalledVersion(): ?string
    {
        try {
            $binPath = $this->binaryResolver->getBinaryPath();
        } catch (RuntimeException) {
            return null;
        }

        if (!file_exists($binPath)) {
            return null;
        }

        $output = shell_exec(escapeshellarg($binPath) . ' --version 2>&1');

        if ($output === null || $output === false) {
            return null;
        }

        // Extract version from output
        $matchResult = preg_match('/v?(\d+\.\d+\.\d+)/', $output, $matches);
        if ($matchResult === 1) {
            return $matches[1];
        }

        return 'unknown';
    }

    public function isSameMajorVersion(string $version1, string $version2): bool
    {
        return $this->getMajorVersion($version1) === $this->getMajorVersion($version2);
    }

    public function getMajorVersion(string $version): int
    {
        $version = ltrim($version, 'v');
        $parts = explode('.', $version);

        /** @phpstan-ignore-next-line */
        if (count($parts) < 1 || !is_numeric($parts[0])) {
            throw new RuntimeException("Invalid version format: {$version}");
        }

        return (int)$parts[0];
    }

    public function cleanupOldVersions(string $currentVersion): void
    {
        $binariesDir = $this->binaryResolver->getBinariesDirectory();

        if (!is_dir($binariesDir)) {
            return;
        }

        $pattern = $binariesDir . '/parallite-*';
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $basename = basename($file);

            $matchResult = preg_match('/parallite-(\d+\.\d+\.\d+)(?:\.\w+)?$/', $basename, $matches);
            if ($matchResult === 1) {
                $fileVersion = $matches[1];

                if ($fileVersion === $currentVersion) {
                    continue;
                }
            }

            @unlink($file);
        }
    }
}
