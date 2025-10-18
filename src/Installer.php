<?php

declare(strict_types=1);

namespace Parallite;

use Parallite\Service\ConfigService;
use Parallite\Service\BinaryResolverService;
use RuntimeException;
use ZipArchive;

/**
 * Handles downloading and installing the Parallite binary from GitHub releases.
 */
final class Installer
{
    private const string GITHUB_REPO = 'b7s/parallite';
    private const string GITHUB_API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

    /**
     * Install the Parallite binary for the current platform.
     *
     * @param bool        $force   Force reinstall even if binary exists
     * @param string|null $version Specific version to install (e.g., '1.2.3' or 'v1.2.3')
     */
    public static function install(bool $force = false, ?string $version = null): void
    {
        if ($version !== null) {
            $version = ltrim($version, 'v');
            echo "[Force] Installing specific version: {$version}...\n";
            self::installVersion($version);
            return;
        }

        echo "Installing Parallite binary...\n";

        $platformInfo = self::detectPlatform();
        $platform = $platformInfo['platform'];
        $extension = $platformInfo['extension'];

        echo "Detected platform: {$platform}\n";

        // Get version from latest release
        $latestVersion = self::checkForUpdates();
        if ($latestVersion === null) {
            throw new RuntimeException('Failed to fetch latest release from GitHub.');
        }

        $latestVersion = ltrim($latestVersion, 'v');

        $binPath = self::getBinPath($latestVersion);

        // Clean up old versions
        self::cleanupOldVersions($latestVersion);

        if (!$force && file_exists($binPath)) {
            echo "Parallite binary already installed at: {$binPath}\n";
            echo "Use --force to reinstall.\n";
            return;
        }

        $downloadUrl = self::getDownloadUrl($platform, $extension);
        echo "Downloading from: {$downloadUrl}\n";

        self::downloadAndExtract($downloadUrl, $binPath, $platform, $extension, $latestVersion);

        // Clear binary cache
        $binResolver = new BinaryResolverService();
        $binResolver->clearCache();

        echo "✓ Parallite binary installed successfully at: {$binPath}\n";
    }

    /**
     * Update the Parallite binary to the latest version.
     * Only updates within the same major version to prevent breaking changes.
     *
     * @param bool        $force   Force update even across major versions
     * @param string|null $version Specific version to install (e.g., '1.2.3' or 'v1.2.3')
     */
    public static function update(bool $force = false, ?string $version = null): void
    {
        // Clear cache before update
        $binResolver = new BinaryResolverService();
        $binResolver->clearCache();

        // If specific version is requested, install it directly
        if ($version !== null) {
            $version = ltrim($version, 'v');
            echo "[Force] Installing specific version: {$version}...\n";
            self::installVersion($version);
            return;
        }

        echo "Updating Parallite binary to latest version...\n";

        $currentVersion = self::getInstalledVersion();

        if ($currentVersion === null) {
            echo "No current version found. Installing latest version...\n";
            self::install(force: true);
            return;
        }

        if ($currentVersion === 'unknown') {
            echo "Warning: Could not determine current version. Proceeding with update...\n";
            self::install(force: true);
            return;
        }

        $latestVersion = self::checkForUpdates();

        if ($latestVersion === null) {
            echo "Could not check for updates. Please try again later.\n";
            return;
        }

        // Remove 'v' prefix if present
        $latestVersion = ltrim($latestVersion, 'v');

        if (!self::isSameMajorVersion($currentVersion, $latestVersion)) {
            if ($force) {
                echo "⚠ Warning: Forcing update across major versions ({$currentVersion} → {$latestVersion})\n";
                echo "  This may include breaking changes. Proceed with caution.\n";
            } else {
                echo "✗ Update blocked: Current version {$currentVersion} cannot be updated to {$latestVersion}\n";
                echo "  Breaking changes detected. Major version updates must be done manually.\n";
                echo '  Current major version: ' . self::getMajorVersion($currentVersion) . "\n";
                echo '  Latest major version: ' . self::getMajorVersion($latestVersion) . "\n";
                echo "  Use --force to override this check or specify a version with --version=X.Y.Z\n";
                return;
            }
        }

        // Clean up old versions
        self::cleanupOldVersions($currentVersion);

        if (version_compare($currentVersion, $latestVersion, '>=')) {
            echo "✓ Already up to date (version {$currentVersion})\n";
            return;
        }

        echo "Updating from {$currentVersion} to {$latestVersion}...\n";
        self::install(force: true);
    }

    /**
     * Get the installation path for the binary.
     */
    private static function getBinPath(string $version): string
    {
        $resolver = new BinaryResolverService();
        $binariesDir = $resolver->getBinariesDirectory();

        if (!is_dir($binariesDir)) {
            mkdir($binariesDir, 0755, true);
        }

        return $binariesDir . "/parallite-{$version}";
    }

    /**
     * Detect the current platform and architecture.
     *
     * @return array{platform: string, extension: string}
     */
    private static function detectPlatform(): array
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        // Normalize architecture names
        $arch = match ($arch) {
            'x86_64', 'AMD64' => 'amd64',
            'aarch64', 'arm64' => 'arm64',
            default => throw new RuntimeException("Unsupported architecture: {$arch}"),
        };

        // Map OS to platform names used in Parallite releases
        $platform = match ($os) {
            'Linux' => "linux-{$arch}",
            'Darwin' => "darwin-{$arch}",
            'Windows' => "windows-{$arch}",
            default => throw new RuntimeException("Unsupported OS: {$os}"),
        };

        $extension = $os === 'Windows' ? 'zip' : 'tar.gz';

        return ['platform' => $platform, 'extension' => $extension];
    }

    /**
     * Install a specific version of Parallite.
     */
    private static function installVersion(string $version): void
    {
        $binPath = self::getBinPath($version);
        $platformInfo = self::detectPlatform();
        $platform = $platformInfo['platform'];
        $extension = $platformInfo['extension'];

        echo "Detected platform: {$platform}\n";

        $downloadUrl = self::getDownloadUrlForVersion($version, $platform, $extension);
        echo "Downloading from: {$downloadUrl}\n";

        self::downloadAndExtract($downloadUrl, $binPath, $platform, $extension, $version);

        // Clean up old versions
        self::cleanupOldVersions($version);

        // Clear binary cache
        $resolver = new BinaryResolverService();
        $resolver->clearCache();

        echo "✓ Parallite binary version {$version} installed successfully at: {$binPath}\n";
    }

    /**
     * Get the download URL for the specified platform from GitHub releases.
     */
    private static function getDownloadUrl(string $platform, string $extension): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Parallite-PHP-Installer',
                    'Accept: application/vnd.github.v3+json',
                ],
            ],
        ]);

        $response = @file_get_contents(self::GITHUB_API_URL, false, $context);

        if ($response === false) {
            throw new RuntimeException(
                'Failed to fetch latest release from GitHub. Please check your internet connection.'
            );
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new RuntimeException('Invalid response from GitHub API');
        }

        if (!isset($release['assets']) || !is_array($release['assets'])) {
            throw new RuntimeException('Invalid response from GitHub API');
        }

        // Find the asset matching the platform and extension
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !isset($asset['name'], $asset['browser_download_url'])) {
                continue;
            }
            if (!is_string($asset['name']) || !is_string($asset['browser_download_url'])) {
                continue;
            }
            if (str_contains($asset['name'], $platform) && str_ends_with($asset['name'], $extension)) {
                return $asset['browser_download_url'];
            }
        }

        throw new RuntimeException(
            "No binary found for platform: {$platform}.{$extension}. Available assets: " .
            implode(', ', array_column($release['assets'], 'name'))
        );
    }

    /**
     * Get the download URL for a specific version.
     */
    private static function getDownloadUrlForVersion(string $version, string $platform, string $extension): string
    {
        $version = ltrim($version, 'v');
        $apiUrl = 'https://api.github.com/repos/' . self::GITHUB_REPO . "/releases/tags/v{$version}";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Parallite-PHP-Installer',
                    'Accept: application/vnd.github.v3+json',
                ],
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            throw new RuntimeException(
                "Failed to fetch release v{$version} from GitHub. Please check the version number and your internet connection."
            );
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new RuntimeException("Invalid response from GitHub API for version v{$version}");
        }

        if (!isset($release['assets']) || !is_array($release['assets'])) {
            throw new RuntimeException("No assets found for version v{$version}");
        }

        // Find the asset matching the platform and extension
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !isset($asset['name'], $asset['browser_download_url'])) {
                continue;
            }
            if (!is_string($asset['name']) || !is_string($asset['browser_download_url'])) {
                continue;
            }
            if (str_contains($asset['name'], $platform) && str_ends_with($asset['name'], $extension)) {
                return $asset['browser_download_url'];
            }
        }

        throw new RuntimeException(
            "No binary found for platform {$platform}.{$extension} in version v{$version}. Available assets: " .
            implode(', ', array_column($release['assets'], 'name'))
        );
    }

    /**
     * Download and extract the binary archive.
     */
    private static function downloadAndExtract(string $url, string $destination, string $platform, string $extension, string $version): void
    {
        $tmpDir = sys_get_temp_dir();
        $archivePath = $tmpDir . '/parallite-' . uniqid() . '.' . $extension;

        // Download archive
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Parallite-PHP-Installer',
                'follow_location' => 1,
            ],
        ]);

        $archive = @file_get_contents($url, false, $context);

        if ($archive === false) {
            throw new RuntimeException("Failed to download archive from: {$url}");
        }

        if (file_put_contents($archivePath, $archive) === false) {
            throw new RuntimeException("Failed to write archive to: {$archivePath}");
        }

        // Extract archive
        try {
            if ($extension === 'zip') {
                self::extractZip($archivePath, $destination, $platform);
            } else {
                self::extractTarGz($archivePath, $destination, $platform);
            }
        } finally {
            @unlink($archivePath);
        }

        // Make binary executable (Unix-like systems)
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($destination, 0755);
        }
    }

    /**
     * Extract tar.gz archive and find the binary.
     */
    private static function extractTarGz(string $archivePath, string $destination, string $platform): void
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

            // Find the binary file (with or without .exe)
            $binaryName = "parallite-{$platform}";
            $binaryPath = $tmpDir . '/' . $binaryName;

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
        } finally {
            // Cleanup
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*');
                if ($files !== false) {
                    array_map('unlink', $files);
                }
                rmdir($tmpDir);
            }
        }
    }

    /**
     * Extract zip archive and find the binary.
     */
    private static function extractZip(string $archivePath, string $destination, string $platform): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is required for Windows installation');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Failed to open zip archive');
        }

        $tmpDir = sys_get_temp_dir() . '/parallite-extract-' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            $zip->extractTo($tmpDir);
            $zip->close();

            // Find the binary file (with or without .exe)
            $binaryName = "parallite-{$platform}";
            $binaryPath = $tmpDir . '/' . $binaryName;

            // Check for .exe version on Windows
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
        } finally {
            // Cleanup
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*');
                if ($files !== false) {
                    array_map('unlink', $files);
                }
                rmdir($tmpDir);
            }
        }
    }

    /**
     * Get the current installed version of Parallite.
     */
    public static function getInstalledVersion(): ?string
    {
        try {
            $resolver = new BinaryResolverService();
            $binPath = $resolver->getBinaryPath();
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

    /**
     * Check if a newer version is available.
     */
    public static function checkForUpdates(): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Parallite-PHP-Installer',
                    'Accept: application/vnd.github.v3+json',
                ],
            ],
        ]);

        $response = @file_get_contents(self::GITHUB_API_URL, false, $context);

        if ($response === false) {
            return null;
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            return null;
        }

        $tagName = $release['tag_name'] ?? null;
        return is_string($tagName) ? $tagName : null;
    }

    /**
     * Check if two versions belong to the same major version.
     */
    private static function isSameMajorVersion(string $version1, string $version2): bool
    {
        return self::getMajorVersion($version1) === self::getMajorVersion($version2);
    }

    /**
     * Extract the major version number from a semantic version string.
     */
    private static function getMajorVersion(string $version): int
    {
        $version = ltrim($version, 'v');
        $parts = explode('.', $version);

        /** @phpstan-ignore-next-line */
        if (count($parts) < 1 || !is_numeric($parts[0])) {
            throw new RuntimeException("Invalid version format: {$version}");
        }

        return (int)$parts[0];
    }

    /**
     * Clean up old binary versions, keeping only the current one
     */
    private static function cleanupOldVersions(string $currentVersion): void
    {
        $resolver = new BinaryResolverService();
        $binariesDir = $resolver->getBinariesDirectory();

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
