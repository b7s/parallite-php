<?php

declare(strict_types=1);

namespace Parallite\Service\Install;

use Parallite\Service\Parallite\BinaryResolverService;
use RuntimeException;

final class BinaryInstallerService
{
    private BinaryResolverService $binaryResolver;

    private GitHubReleaseService $githubService;

    private FileExtractorService $fileExtractor;

    private VersionService $versionService;

    public function __construct(
        BinaryResolverService $binaryResolver,
        GitHubReleaseService $githubService,
        FileExtractorService $fileExtractor,
        VersionService $versionService
    ) {
        $this->binaryResolver = $binaryResolver;
        $this->githubService = $githubService;
        $this->fileExtractor = $fileExtractor;
        $this->versionService = $versionService;
    }

    public function install(bool $force = false, ?string $version = null): void
    {
        if ($version !== null) {
            $this->installSpecificVersion($version);

            return;
        }

        echo "Installing Parallite binary...\n";

        $platformInfo = $this->detectPlatform();
        $platform = $platformInfo['platform'];
        $extension = $platformInfo['extension'];

        echo "Detected platform: {$platform}\n";

        // Get version from latest release
        $latestVersion = $this->githubService->getLatestVersion();
        if ($latestVersion === null) {
            throw new RuntimeException('Failed to fetch latest release from GitHub.');
        }

        $latestVersion = ltrim($latestVersion, 'v');
        $binPath = $this->getBinPath($latestVersion);

        if ($force) {
            // If force is true, remove existing binary if it exists
            if (file_exists($binPath)) {
                @unlink($binPath);
            }
        } elseif (file_exists($binPath)) {
            echo "Parallite binary already installed at: {$binPath}\n";
            echo "Use --force to reinstall.\n";

            return;
        }

        // Clean up old versions (except the one we're about to install)
        $this->versionService->cleanupOldVersions($latestVersion);

        $downloadUrl = $this->githubService->getDownloadUrl($platform, $extension);
        echo "Downloading from: {$downloadUrl}\n";

        $this->downloadAndExtract($downloadUrl, $binPath, $platform, $extension, $latestVersion);

        // Clear binary cache
        $this->binaryResolver->clearCache();

        echo "✓ Parallite binary installed successfully at: {$binPath}\n";
    }

    public function installSpecificVersion(string $version): void
    {
        $version = ltrim($version, 'v');
        $platformInfo = $this->detectPlatform();
        $platform = $platformInfo['platform'];
        $extension = $platformInfo['extension'];

        $binPath = $this->getBinPath($version);
        $downloadUrl = $this->githubService->getDownloadUrlForVersion($version, $platform, $extension);

        echo "Downloading from: {$downloadUrl}\n";
        $this->downloadAndExtract($downloadUrl, $binPath, $platform, $extension, $version);

        // Clean up old versions
        $this->versionService->cleanupOldVersions($version);

        // Clear binary cache
        $this->binaryResolver->clearCache();

        echo "✓ Parallite binary version {$version} installed successfully at: {$binPath}\n";
    }

    public function update(bool $force = false, ?string $version = null): void
    {
        // Clear cache before update
        $this->binaryResolver->clearCache();

        // If specific version is requested, install it directly
        if ($version !== null) {
            $version = ltrim($version, 'v');
            echo "[Force] Installing specific version: {$version}...\n";
            $this->installSpecificVersion($version);

            return;
        }

        echo "Updating Parallite binary to latest version...\n";

        $currentVersion = $this->versionService->getInstalledVersion();

        if ($currentVersion === null) {
            echo "No current version found. Installing latest version...\n";
            $this->install(force: true);

            return;
        }

        if ($currentVersion === 'unknown') {
            echo "Warning: Could not determine current version. Proceeding with update...\n";
            $this->install(force: true);

            return;
        }

        $latestVersion = $this->githubService->getLatestVersion();

        if ($latestVersion === null) {
            echo "Could not check for updates. Please try again later.\n";

            return;
        }

        // Remove 'v' prefix if present
        $latestVersion = ltrim($latestVersion, 'v');

        if (! $this->versionService->isSameMajorVersion($currentVersion, $latestVersion)) {
            if ($force) {
                echo "⚠ Warning: Forcing update across major versions ({$currentVersion} → {$latestVersion})\n";
                echo "  This may include breaking changes. Proceed with caution.\n";
            } else {
                $currentMajor = $this->versionService->getMajorVersion($currentVersion);
                $latestMajor = $this->versionService->getMajorVersion($latestVersion);

                echo "✗ Update blocked: Current version {$currentVersion} cannot be updated to {$latestVersion}\n";
                echo "  Breaking changes detected. Major version updates must be done manually.\n";
                echo "  Current major version: {$currentMajor}\n";
                echo "  Latest major version: {$latestMajor}\n";
                echo "  Use --force to override this check or specify a version with --version=X.Y.Z\n";

                return;
            }
        }

        // Clean up old versions
        $this->versionService->cleanupOldVersions($currentVersion);

        if (version_compare($currentVersion, $latestVersion, '>=')) {
            echo "✓ Already up to date (version {$currentVersion})\n";

            return;
        }

        echo "Updating from {$currentVersion} to {$latestVersion}...\n";
        $this->install(force: true);
    }

    private function getBinPath(string $version): string
    {
        $binariesDir = $this->binaryResolver->getBinariesDirectory();

        if (! is_dir($binariesDir)) {
            mkdir($binariesDir, 0755, true);
        }

        return $binariesDir."/parallite-{$version}";
    }

    /**
     * Detect the current platform and architecture.
     *
     * @return array{platform: string, extension: string}
     */
    private function detectPlatform(): array
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

    private function downloadAndExtract(string $url, string $destination, string $platform, string $extension, string $version): void
    {
        $tmpDir = sys_get_temp_dir();
        $archivePath = $tmpDir.'/parallite-'.uniqid().'.'.$extension;

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
            $this->fileExtractor->extractArchive($archivePath, $destination, $platform, $extension);
        } finally {
            @unlink($archivePath);
        }
    }

    /**
     * Get the current installed version of Parallite.
     */
    public function getInstalledVersion(): ?string
    {
        return $this->versionService->getInstalledVersion();
    }

    /**
     * Check if a newer version is available.
     */
    public function checkForUpdates(): ?string
    {
        return $this->githubService->getLatestVersion();
    }
}
