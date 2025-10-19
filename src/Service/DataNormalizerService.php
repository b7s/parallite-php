<?php

declare(strict_types=1);

namespace Parallite\Service;

use DateTimeInterface;
use RuntimeException;

/**
 * Data Normalizer for MessagePack Serialization
 *
 * Ensures data structures are compatible with MessagePack serialization
 * by normalizing types and structures that can cause issues.
 */
final class DataNormalizerService
{
    /**
     * Normalize data for safe MessagePack serialization
     *
     * @param mixed $data         Data to normalize
     * @param int   $maxDepth     Maximum recursion depth (prevents infinite loops)
     * @param int   $currentDepth Current recursion depth
     * @return mixed Normalized data
     * @throws RuntimeException If data contains non-serializable types
     */
    public static function normalize(mixed $data, int $maxDepth = 100, int $currentDepth = 0): mixed
    {
        if ($currentDepth >= $maxDepth) {
            throw new RuntimeException("Maximum recursion depth ({$maxDepth}) exceeded during normalization");
        }

        return match (true) {
            is_array($data) => self::normalizeArray($data, $maxDepth, $currentDepth),
            is_object($data) => self::normalizeObject($data, $maxDepth, $currentDepth),
            is_resource($data) => throw new RuntimeException('Resources cannot be serialized'),
            is_float($data) && (is_nan($data) || is_infinite($data)) => null,
            default => $data,
        };
    }

    /**
     * Normalize array for MessagePack
     *
     * MessagePack has issues with:
     * - Mixed integer/string keys
     * - Large sparse arrays
     * - Non-sequential integer keys
     *
     * @phpstan-ignore-next-line
     */
    private static function normalizeArray(array $data, int $maxDepth, int $currentDepth): array
    {
        if (count($data) === 0) {
            return $data;
        }

        // Check if array has mixed key types or non-sequential integer keys
        $hasStringKeys = false;
        $hasIntKeys = false;
        $isSequential = true;
        $expectedKey = 0;

        foreach (array_keys($data) as $key) {
            if (is_string($key)) {
                $hasStringKeys = true;
            } elseif (is_int($key)) {
                $hasIntKeys = true;
                if ($key !== $expectedKey) {
                    $isSequential = false;
                }
                $expectedKey++;
            }
        }

        // Handle different array types
        if ($hasStringKeys && !$hasIntKeys) {
            // Pure associative array (string keys only) - safe, keep as is
            return array_map(function ($value) use ($maxDepth, $currentDepth) {
                return self::normalize($value, $maxDepth, $currentDepth + 1);
            }, $data);
        }

        $normalized = [];
        foreach ($data as $value) {
            $normalized[] = self::normalize($value, $maxDepth, $currentDepth + 1);
        }
        return $normalized;
    }

    /**
     * Normalize object for MessagePack.
     *
     * Converts objects to arrays to avoid serialization issues.
     *
     * @param object $data         Object to normalize
     * @param int    $maxDepth     Maximum recursion depth (prevents infinite loops)
     * @param int    $currentDepth Current recursion depth
     * @return array<int|string, mixed> Normalized object as array
     */
    private static function normalizeObject(object $data, int $maxDepth, int $currentDepth): array
    {
        // Handle common Laravel/Eloquent objects
        if (method_exists($data, 'toArray')) {
            $array = $data->toArray();
            return self::normalizeArray($array, $maxDepth, $currentDepth + 1);
        }

        // Handle DateTime objects
        if ($data instanceof DateTimeInterface) {
            return [
                '_type' => 'datetime',
                'value' => $data->format('c'),
                'timezone' => $data->getTimezone()->getName(),
            ];
        }

        // Convert stdClass and other objects to arrays
        $array = (array)$data;
        return self::normalizeArray($array, $maxDepth, $currentDepth + 1);
    }

    /**
     * Check if data is safe for MessagePack serialization
     *
     * @param mixed $data Data to check
     * @return bool True if data appears safe to serialize
     */
    public static function isSafe(mixed $data): bool
    {
        try {
            self::normalize($data);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
