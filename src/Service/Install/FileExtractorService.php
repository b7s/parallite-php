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
        $tmpDir = sys_get_temp_dir().'/parallite-extract-'.bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
        }

        try {
            $command = [
                'tar',
                '-xzf',
                $archivePath,
                '-C',
                $tmpDir,
            ];

            $process = proc_open($command, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (! is_resource($process)) {
                throw new RuntimeException('Failed to start tar extraction process');
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                throw new RuntimeException('Failed to extract tar.gz: '.trim((string) $stdout));
            }

            $this->moveBinary($tmpDir, $destination, $platform);
        } finally {
            $this->cleanupDirectory($tmpDir);
        }
    }

    private function extractZip(string $archivePath, string $destination, string $platform): void
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is required for Windows installation');
        }

        $zip = new ZipArchive;
        $tmpDir = sys_get_temp_dir().'/parallite-extract-'.bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
        }

        try {
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('Failed to open zip archive');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if ($filename === false) {
                    continue;
                }

                if (str_contains($filename, '../') || str_contains($filename, '..\\')) {
                    $zip->close();
                    throw new RuntimeException('Path traversal detected in ZIP file');
                }

                $startsWithSlash = str_starts_with($filename, '/') || str_starts_with($filename, '\\');
                if ($startsWithSlash) {
                    $zip->close();
                    throw new RuntimeException('Absolute path detected in ZIP file');
                }

                $isDirectory = str_ends_with($filename, '/');
                if (! $isDirectory) {
                    $stat = $zip->statIndex($i);
                    if ($stat !== false && ($stat['crc'] === 0 || $stat['size'] === 0)) {
                        $zip->close();
                        throw new RuntimeException('Suspicious file detected in ZIP');
                    }
                }
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
        $binaryPath = $sourceDir.'/'.$binaryName;

        // On Windows, check for .exe version if needed
        if (! file_exists($binaryPath) && ConfigService::isWindows()) {
            $binaryPath .= '.exe';
            if (! file_exists($binaryPath)) {
                throw new RuntimeException("Binary not found in archive: {$binaryName} or {$binaryName}.exe");
            }
        } elseif (! file_exists($binaryPath)) {
            throw new RuntimeException("Binary not found in archive: {$binaryName}");
        }

        // Move to destination (without .exe)
        if (! rename($binaryPath, $destination)) {
            throw new RuntimeException("Failed to move binary to: {$destination}");
        }
    }

    private function cleanupDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*');
        if ($files !== false) {
            array_map('unlink', $files);
        }
        @rmdir($directory);
    }
}
