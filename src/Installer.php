<?php

declare(strict_types=1);

namespace Parallite;

use Parallite\Service\Install\BinaryInstallerService;
use Parallite\Service\Install\FileExtractorService;
use Parallite\Service\Install\GitHubReleaseService;
use Parallite\Service\Install\VersionService;
use Parallite\Service\Parallite\BinaryResolverService;

/**
 * Handles downloading and installing the Parallite binary from GitHub releases.
 */
final class Installer
{
    private static ?BinaryInstallerService $installer = null;

    private static function getInstaller(): BinaryInstallerService
    {
        if (self::$installer === null) {
            $binaryResolver = new BinaryResolverService;
            $githubService = new GitHubReleaseService;
            $fileExtractor = new FileExtractorService;
            $versionService = new VersionService($binaryResolver);

            self::$installer = new BinaryInstallerService(
                $binaryResolver,
                $githubService,
                $fileExtractor,
                $versionService
            );
        }

        return self::$installer;
    }

    /**
     * Install the Parallite binary for the current platform.
     *
     * @param  bool  $force  Force reinstall even if binary exists
     * @param  string|null  $version  Specific version to install (e.g., '1.2.3' or 'v1.2.3')
     */
    public static function install(bool $force = false, ?string $version = null): void
    {
        self::getInstaller()->install($force, $version);
    }

    /**
     * Update the Parallite binary to the latest version.
     * Only updates within the same major version to prevent breaking changes.
     *
     * @param  bool  $force  Force update even across major versions
     * @param  string|null  $version  Specific version to install (e.g., '1.2.3' or 'v1.2.3')
     */
    public static function update(bool $force = false, ?string $version = null): void
    {
        self::getInstaller()->update($force, $version);
    }

    /**
     * Get the current installed version of Parallite.
     */
    public static function getInstalledVersion(): ?string
    {
        return self::getInstaller()->getInstalledVersion();
    }

    /**
     * Check if a newer version is available.
     */
    public static function checkForUpdates(): ?string
    {
        return self::getInstaller()->checkForUpdates();
    }
}
