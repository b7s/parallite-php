<?php

declare(strict_types=1);

namespace Parallite;

/**
 * Benchmark data returned by Parallite daemon
 * 
 * Contains performance metrics about task execution including:
 * - Execution time
 * - Memory delta (change during task)
 * - Memory peak
 * - CPU time
 */
readonly class BenchmarkData
{
    public function __construct(
        public float $executionTimeMs,
        public float $memoryDeltaMb,
        public float $memoryPeakMb,
        public float $cpuTimeMs,
    ) {}

    /**
     * Create from daemon response array
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $executionTime = $data['execution_time_ms'] ?? 0.0;
        $memoryDelta = $data['memory_delta_mb'] ?? 0.0;
        $memoryPeak = $data['memory_peak_mb'] ?? 0.0;
        $cpuTime = $data['cpu_time_ms'] ?? 0.0;
        
        return new self(
            executionTimeMs: is_numeric($executionTime) ? (float)$executionTime : 0.0,
            memoryDeltaMb: is_numeric($memoryDelta) ? (float)$memoryDelta : 0.0,
            memoryPeakMb: is_numeric($memoryPeak) ? (float)$memoryPeak : 0.0,
            cpuTimeMs: is_numeric($cpuTime) ? (float)$cpuTime : 0.0,
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'execution_time_ms' => $this->executionTimeMs,
            'memory_delta_mb' => $this->memoryDeltaMb,
            'memory_peak_mb' => $this->memoryPeakMb,
            'cpu_time_ms' => $this->cpuTimeMs,
        ];
    }

    /**
     * Format as human-readable string
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            "Execution: %.2fms | Memory Δ: %.2fMB | Peak: %.2fMB | CPU: %.2fms",
            $this->executionTimeMs,
            $this->memoryDeltaMb,
            $this->memoryPeakMb,
            $this->cpuTimeMs
        );
    }
}
