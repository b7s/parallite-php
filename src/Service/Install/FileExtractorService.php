<?php

declare(strict_types=1);

namespace Parallite\Service\Install;

use Parallite\Service\Parallite\ConfigService;
use RuntimeException;
use ZipArchive;

final class FileExtractorService
{
    public function extractArchive(string $archivePath, string $destination, string $platform, string $extension): void
    {
        if ($extension === 'zip') {
            $this->extractZip($archivePath, $destination, $platform);
        } else {
            $this->extractTarGz($archivePath, $destination, $platform);
        }

        // Make binary executable (Unix-like systems)
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($destination, 0755);
        }
    }

    private function extractTarGz(string $archivePath, string $destination, string $platform): void
    {
        $tmpDir = sys_get_temp_dir() . '/parallite-extract-' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            // Extract using tar command
            $command = sprintf(
                'tar -xzf %s -C %s 2>&1',
                escapeshellarg($archivePath),
                escapeshellarg($tmpDir)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new RuntimeException('Failed to extract tar.gz: ' . implode("\n", $output));
            }

            $this->moveBinary($tmpDir, $destination, $platform);
        } finally {
            $this->cleanupDirectory($tmpDir);
        }
    }

    private function extractZip(string $archivePath, string $destination, string $platform): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is required for Windows installation');
        }

        $zip = new ZipArchive();
        $tmpDir = sys_get_temp_dir() . '/parallite-extract-' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('Failed to open zip archive');
            }

            $zip->extractTo($tmpDir);
            $zip->close();

            $this->moveBinary($tmpDir, $destination, $platform);
        } finally {
            $this->cleanupDirectory($tmpDir);
        }
    }

    private function moveBinary(string $sourceDir, string $destination, string $platform): void
    {
        $binaryName = "parallite-{$platform}";
        $binaryPath = $sourceDir . '/' . $binaryName;

        // On Windows, check for .exe version if needed
        if (!file_exists($binaryPath) && ConfigService::isWindows()) {
            $binaryPath .= '.exe';
            if (!file_exists($binaryPath)) {
                throw new RuntimeException("Binary not found in archive: {$binaryName} or {$binaryName}.exe");
            }
        } elseif (!file_exists($binaryPath)) {
            throw new RuntimeException("Binary not found in archive: {$binaryName}");
        }

        // Move to destination (without .exe)
        if (!rename($binaryPath, $destination)) {
            throw new RuntimeException("Failed to move binary to: {$destination}");
        }
    }

    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*');
        if ($files !== false) {
            array_map('unlink', $files);
        }
        @rmdir($directory);
    }
}
