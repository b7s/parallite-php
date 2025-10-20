<?php

declare(strict_types=1);

namespace Parallite\Service\Install;

use RuntimeException;

final class GitHubReleaseService
{
    private const string GITHUB_REPO = 'b7s/parallite';
    private const string GITHUB_API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/';

    /**
     * Get the download URL for the latest release for the specified platform.
     */
    public function getDownloadUrl(string $platform, string $extension): string
    {
        return $this->getDownloadUrlForVersion('latest', $platform, $extension);
    }

    /**
     * Get the download URL for a specific version.
     */
    public function getDownloadUrlForVersion(string $version, string $platform, string $extension): string
    {
        $version = ltrim($version, 'v');
        $endpoint = $version === 'latest'
            ? 'latest'
            : 'tags/v' . $version;

        $release = $this->fetchRelease($endpoint);
        $release['assets'] = (array)$release['assets'];

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
     * Check for the latest release version.
     */
    public function getLatestVersion(): ?string
    {
        $release = $this->fetchRelease('latest');
        $tagName = $release['tag_name'] ?? null;
        return is_string($tagName) ? $tagName : null;
    }

    /**
     * Fetch a release from GitHub API.
     *
     * @return array<string, mixed>
     */
    private function fetchRelease(string $endpoint): array
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

        $url = self::GITHUB_API_URL . $endpoint;
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException(
                'Failed to fetch release from GitHub: ' . ($error['message'] ?? 'Unknown error')
            );
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new RuntimeException('Invalid response from GitHub API');
        }

        if (!isset($release['assets']) || !is_array($release['assets'])) {
            throw new RuntimeException('No assets found in the release');
        }

        return $release;
    }
}
