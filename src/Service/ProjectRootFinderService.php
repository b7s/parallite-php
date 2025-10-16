<?php

declare(strict_types=1);

namespace Parallite\Service;

/**
 * Utility class for finding the project root directory
 */
final class ProjectRootFinderService
{
    /**
     * Find project root by looking for vendor/autoload.php
     * 
     * @param string|null $startDir Starting directory (defaults to caller's directory)
     */
    public static function find(?string $startDir = null): string
    {
        $dir = $startDir ?? dirname(__DIR__, 2);
        $maxLevels = 10;

        for ($i = 0; $i < $maxLevels; $i++) {
            if (file_exists($dir.'/vendor/autoload.php')) {
                return $dir;
            }

            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break; // Reached filesystem root
            }

            $dir = $parentDir;
        }

        // Fallback to 2 levels up from src/
        return dirname(__DIR__, 2);
    }
}
