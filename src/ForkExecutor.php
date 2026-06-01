<?php

declare(strict_types=1);

namespace Parallite;

use const JSON_UNESCAPED_SLASHES;
use const SIGKILL;

use Closure;
use RuntimeException;
use Throwable;

use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function pcntl_fork;
use function pcntl_waitpid;
use function posix_getpid;
use function posix_kill;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

readonly class ForkExecutor
{
    public static function isAvailable(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_getpid');
    }

    /**
     * @param  Closure(): mixed  $closure
     * @return array{pid: int, temp_file: string}
     *
     * @throws RuntimeException
     */
    public function fork(Closure $closure, bool $enableBenchmark = false): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'parallite_');

        if ($tempFile === false) {
            throw new RuntimeException('Failed to create temporary file for fork result');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            @unlink($tempFile);
            throw new RuntimeException('pcntl_fork() failed');
        }

        if ($pid === 0) {
            $benchmark = null;

            if ($enableBenchmark) {
                $benchmark = $this->startBenchmark();
            }

            try {
                $result = $closure();
                $data = ['ok' => true, 'result' => $result];
            } catch (Throwable $e) {
                $data = ['ok' => false, 'error' => $e->getMessage()];
            }

            if ($benchmark !== null) {
                $data['benchmark'] = $this->finishBenchmark($benchmark);
            }

            file_put_contents($tempFile, json_encode($data, JSON_UNESCAPED_SLASHES));
            posix_kill(posix_getpid(), SIGKILL);
        }

        return ['pid' => $pid, 'temp_file' => $tempFile];
    }

    /**
     * @param  array{pid: int, temp_file: string, benchmark?: array<string, mixed>}  $handle
     *
     * @param-out array{pid: int, temp_file: string, benchmark?: array<string, mixed>} $handle
     *
     * @throws RuntimeException
     */
    public function awaitOne(array &$handle): mixed
    {
        pcntl_waitpid($handle['pid'], $status);

        /** @var array{ok?: bool, result?: mixed, error?: string, benchmark?: array<string, mixed>} $data */
        $data = $this->readResult($handle['temp_file']);

        if (isset($data['benchmark']) && is_array($data['benchmark'])) {
            $handle['benchmark'] = $data['benchmark'];
        }

        if (($data['ok'] ?? false) === true) {
            return $data['result'] ?? null;
        }

        $errorMsg = $data['error'] ?? 'Unknown fork error';

        throw new RuntimeException(is_string($errorMsg) ? $errorMsg : 'Unknown fork error');
    }

    /**
     * @param  array<array{pid: int, temp_file: string, benchmark?: array<string, mixed>}>  $handles
     *
     * @param-out array<array{pid: int, temp_file: string, benchmark?: array<string, mixed>}> $handles
     *
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    public function awaitAll(array &$handles): array
    {
        if ($handles === []) {
            return [];
        }

        foreach ($handles as $handle) {
            pcntl_waitpid($handle['pid'], $status);
        }

        $results = [];

        foreach ($handles as $i => $handle) {
            /** @var array{ok?: bool, result?: mixed, error?: string, benchmark?: array<string, mixed>} $data */
            $data = $this->readResult($handle['temp_file']);

            if (isset($data['benchmark']) && is_array($data['benchmark'])) {
                $handles[$i]['benchmark'] = $data['benchmark'];
            }

            if (($data['ok'] ?? false) === true) {
                $results[$i] = $data['result'] ?? null;
            } else {
                $errorMsg = $data['error'] ?? 'Unknown fork error';
                throw new RuntimeException(is_string($errorMsg) ? $errorMsg : 'Unknown fork error');
            }
        }

        return $results;
    }

    /**
     * @return array{start_time: float, start_memory: int, start_rusage: array<string, int>|false}
     */
    private function startBenchmark(): array
    {
        return [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(false),
            'start_rusage' => getrusage(),
        ];
    }

    /**
     * @param  array{start_time: float, start_memory: int, start_rusage: array<string, int>|false}  $benchmark
     * @return array<string, float>
     */
    private function finishBenchmark(array $benchmark): array
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(false);
        $endPeakMemory = memory_get_peak_usage(false);
        $endRusage = getrusage();

        $executionTimeMs = ($endTime - $benchmark['start_time']) * 1000;
        $memoryDelta = $endMemory - $benchmark['start_memory'];
        $peakMemoryUsed = $endPeakMemory;

        $totalCpuTimeMs = 0.0;
        $startRusage = $benchmark['start_rusage'];
        if (is_array($startRusage) && is_array($endRusage)) {
            $userTimeMsStart = ((int) ($startRusage['ru_utime.tv_sec'] ?? 0)) * 1000 + ((int) ($startRusage['ru_utime.tv_usec'] ?? 0)) / 1000;
            $systemTimeMsStart = ((int) ($startRusage['ru_stime.tv_sec'] ?? 0)) * 1000 + ((int) ($startRusage['ru_stime.tv_usec'] ?? 0)) / 1000;
            $userTimeMsEnd = ((int) ($endRusage['ru_utime.tv_sec'] ?? 0)) * 1000 + ((int) ($endRusage['ru_utime.tv_usec'] ?? 0)) / 1000;
            $systemTimeMsEnd = ((int) ($endRusage['ru_stime.tv_sec'] ?? 0)) * 1000 + ((int) ($endRusage['ru_stime.tv_usec'] ?? 0)) / 1000;

            $totalCpuTimeMs = ($userTimeMsEnd - $userTimeMsStart) + ($systemTimeMsEnd - $systemTimeMsStart);
        }

        return [
            'execution_time_ms' => round($executionTimeMs, 3),
            'memory_delta_mb' => round($memoryDelta / 1024 / 1024, 4),
            'memory_peak_mb' => round($peakMemoryUsed / 1024 / 1024, 4),
            'cpu_time_ms' => round($totalCpuTimeMs, 3),
        ];
    }

    /**
     * @return array{ok?: bool, result?: mixed, error?: string, benchmark?: array<string, mixed>}
     *
     * @throws RuntimeException
     */
    private function readResult(string $tempFile): array
    {
        $raw = file_get_contents($tempFile);

        if ($raw === false) {
            @unlink($tempFile);
            throw new RuntimeException('Failed to read fork result file');
        }

        @unlink($tempFile);

        /** @var array{ok?: bool, result?: mixed, error?: string, benchmark?: array<string, mixed>} $data */
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new RuntimeException('Invalid fork result: not an array');
        }

        return $data;
    }
}
