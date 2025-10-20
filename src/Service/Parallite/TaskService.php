<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use Closure;
use Parallite\Promise;
use Socket;
use Throwable;

/**
 * Service responsible for managing parallel task execution
 */
final readonly class TaskService
{
    public function __construct(
        private SocketService $socketService
    ) {}

    /**
     * Await multiple closures in parallel
     *
     * @param  array<Closure>  $closures
     * @return array<mixed>
     *
     * @throws Throwable
     */
    public function awaitAll(array $closures): array
    {
        $futures = [];
        foreach ($closures as $closure) {
            $futures[] = $this->socketService->submitTask($closure);
        }

        $results = [];
        foreach ($futures as $future) {
            $results[] = $this->socketService->awaitTask($future);
        }

        return $results;
    }

    /**
     * Await multiple promises/futures in parallel
     *
     * @param  array<Promise|array{socket: Socket|null, task_id: string}|mixed>  $promises
     * @return array<mixed>
     *
     * @throws Throwable
     */
    public function awaitMultiple(array $promises): array
    {
        $results = [];

        foreach ($promises as $key => $p) {
            if ($p instanceof Promise) {
                $results[$key] = $p->resolve();
            } elseif (is_array($p) && isset($p['socket']) && isset($p['task_id'])) {
                /** @var array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>} $p */
                $results[$key] = $this->socketService->awaitTask($p);
            } else {
                $results[$key] = $p;
            }
        }

        return $results;
    }
}
