<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use RuntimeException;
use Throwable;

class ParalliteClient
{
    private ?ForkExecutor $executor = null;

    private bool $enableBenchmark;

    private bool $forkMode;

    /**
     * @param  bool  $enableBenchmark  If true, includes benchmark data in responses
     * @param  bool  $useFork  If true and pcntl available, use fork mode (default: true)
     */
    public function __construct(
        bool $enableBenchmark = false,
        bool $useFork = true,
    ) {
        $this->enableBenchmark = $enableBenchmark;
        $this->forkMode = $useFork && ForkExecutor::isAvailable();

        if ($this->forkMode) {
            $this->executor = new ForkExecutor;
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $closure
     * @return Promise<TReturn>
     */
    public function promise(Closure $closure): Promise
    {
        return new Promise($this, $closure);
    }

    /**
     * Submit a task for parallel execution
     *
     * @param  Closure  $closure  The closure to execute
     * @return array{pid: int, temp_file: string} Future handle for fork mode
     *
     * @throws RuntimeException
     */
    public function async(Closure $closure): array
    {
        if ($this->executor !== null) {
            return $this->executor->fork($closure, $this->enableBenchmark);
        }

        return $this->sequentialAsync($closure);
    }

    /**
     * Await the result of a previously submitted task
     *
     * @template TReturn
     *
     * @param  array{pid: int, temp_file: string, benchmark?: array<string, mixed>}|Promise<TReturn>|null  $future  The future returned by async() or a Promise
     *
     * @param-out array{pid: int, temp_file: string, benchmark?: array<string, mixed>}|Promise<TReturn>|null $future
     *
     * @return mixed The result of the task execution
     *
     * @throws RuntimeException|Throwable
     */
    public function await(array|Promise|null &$future = null): mixed
    {
        if ($future instanceof Promise) {
            return $future->resolve();
        }

        if (! is_array($future) || ! isset($future['pid'])) {
            throw new RuntimeException('No future provided');
        }

        if ($this->executor !== null) {
            return $this->executor->awaitOne($future);
        }

        return $future['result'] ?? null;
    }

    public function enableBenchmark(): self
    {
        $this->enableBenchmark = true;

        return $this;
    }

    public function disableBenchmark(): self
    {
        $this->enableBenchmark = false;

        return $this;
    }

    public function isBenchmarkEnabled(): bool
    {
        return $this->enableBenchmark;
    }

    public function isForkMode(): bool
    {
        return $this->forkMode;
    }

    /**
     * Await multiple closures in parallel
     *
     * @param  array<Closure>  $closures  Array of closures to execute
     * @return array<mixed> Array of results in the same order
     *
     * @throws Throwable
     */
    public function awaitAll(array $closures): array
    {
        if ($this->executor !== null) {
            $handles = [];
            foreach ($closures as $closure) {
                $handles[] = $this->executor->fork($closure, $this->enableBenchmark);
            }

            return $this->executor->awaitAll($handles);
        }

        $results = [];
        foreach ($closures as $closure) {
            $results[] = $closure();
        }

        return $results;
    }

    /**
     * Await multiple promises/futures in parallel
     *
     * @param  array<Promise|array{pid: int, temp_file: string}|mixed>  $promises  Array of promises, futures, or mixed values
     * @return array<mixed> Array of results (promises resolved, other values pass through)
     *
     * @throws Throwable
     */
    public function awaitMultiple(array $promises): array
    {
        $results = [];

        foreach ($promises as $key => $p) {
            if ($p instanceof Promise) {
                $results[$key] = $p->resolve();
            } elseif (is_array($p) && isset($p['pid'])) {
                /** @var array{pid: int, temp_file: string, benchmark?: array<string, mixed>} $p */
                if ($this->executor !== null) {
                    $results[$key] = $this->executor->awaitOne($p);
                } else {
                    $results[$key] = $p['result'] ?? null;
                }
            } else {
                $results[$key] = $p;
            }
        }

        return $results;
    }

    /**
     * Sequential fallback for async when pcntl is not available
     *
     * @return array{pid: int, temp_file: string, result: mixed}
     */
    private function sequentialAsync(Closure $closure): array
    {
        try {
            $result = $closure();

            return ['pid' => 0, 'temp_file' => '', 'result' => $result];
        } catch (Throwable $e) {
            return ['pid' => 0, 'temp_file' => '', 'result' => new RuntimeException($e->getMessage())];
        }
    }
}
