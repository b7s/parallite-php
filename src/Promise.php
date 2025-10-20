<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use RuntimeException;
use Socket;
use Throwable;

/**
 * Promise wrapper for parallel task execution with chainable then/catch/finally
 *
 * @template TReturn
 */
final class Promise
{
    /**
     * @var array{socket: Socket, task_id: string, benchmark?: array<string, mixed>}|null
     */
    private ?array $future = null;

    /**
     * @var array<array{type: 'then'|'catch'|'finally', callback: Closure}>
     */
    private array $handlers = [];

    private ?BenchmarkData $benchmark = null;

    /**
     * @param  Closure(): TReturn  $callback
     */
    public function __construct(
        private readonly ParalliteClient $client,
        private readonly Closure $callback,
        bool $eager = true
    ) {
        // Start execution immediately for true parallelism
        if ($eager) {
            $this->start();
        }
    }

    /**
     * Allow promise to be invoked directly
     *
     * @return TReturn
     */
    public function __invoke(): mixed
    {
        return $this->resolve();
    }

    /**
     * Start the async execution if not already started
     *
     * @return array{socket: Socket, task_id: string}
     */
    public function start(): array
    {
        if ($this->future !== null) {
            return $this->future;
        }

        $this->future = $this->client->async($this->callback);

        return $this->future;
    }

    /**
     * Get the future (for backward compatibility with await())
     *
     * @return array{socket: Socket, task_id: string}
     */
    public function getFuture(): array
    {
        return $this->start();
    }

    /**
     * Resolve the promise and apply all chained callbacks
     *
     * Follows JavaScript Promise semantics:
     * - then() handlers run sequentially on success
     * - On error, skip to next catch() handler
     * - After catch() handles error, continue with subsequent then() handlers
     * - finally() handlers always run at the end
     *
     * @throws Throwable
     */
    public function resolve(): mixed
    {
        $this->start();

        $result = null;
        $exception = null;
        $isError = false;

        try {
            // Pass future by reference to allow await() to modify it
            $futureRef = &$this->future;
            $result = $this->client->await($futureRef);

            if (isset($this->future['benchmark'])) {
                $this->benchmark = BenchmarkData::fromArray($this->future['benchmark']);
            }
        } catch (Throwable $e) {
            $exception = $e;
            $isError = true;
        }

        // Process handlers in registration order
        foreach ($this->handlers as $handler) {
            if ($handler['type'] === 'then') {
                if (! $isError) {
                    try {
                        $result = $handler['callback']($result);
                    } catch (Throwable $e) {
                        $exception = $e;
                        $isError = true;
                    }
                }
            } elseif ($handler['type'] === 'catch') {
                if ($isError) {
                    try {
                        $result = $handler['callback']($exception);
                        $isError = false;
                    } catch (Throwable $e) {
                        $exception = $e;
                    }
                }
            }
        }

        // Apply finally callbacks (always run, don't modify result)
        foreach ($this->handlers as $handler) {
            if ($handler['type'] === 'finally') {
                $handler['callback']();
            }
        }

        // Throw if still in error state
        if ($isError) {
            if (! ($exception instanceof Throwable)) {
                throw new RuntimeException('Promise rejected without exception instance.');
            }

            throw $exception;
        }

        return $result;
    }

    /**
     * Add a then callback to the promise chain
     *
     * @template TThenReturn
     *
     * @param  Closure(TReturn): TThenReturn  $then
     * @return self<TThenReturn>
     */
    public function then(Closure $then): self
    {
        $this->handlers[] = ['type' => 'then', 'callback' => $then];

        return $this;
    }

    /**
     * Add a catch callback to handle exceptions
     *
     * @template TCatchReturn
     *
     * @param  Closure(Throwable): TCatchReturn  $catch
     * @return self<TReturn|TCatchReturn>
     */
    public function catch(Closure $catch): self
    {
        $this->handlers[] = ['type' => 'catch', 'callback' => $catch];

        return $this;
    }

    /**
     * Add a final callback that runs regardless of success/failure
     *
     * @param  Closure(): void  $finally
     * @return self<TReturn>
     */
    public function finally(Closure $finally): self
    {
        $this->handlers[] = ['type' => 'finally', 'callback' => $finally];

        return $this;
    }

    /**
     * Get benchmark data if available
     *
     * Returns null if benchmark mode was not enabled or if promise hasn't been resolved yet.
     */
    public function getBenchmark(): ?BenchmarkData
    {
        return $this->benchmark;
    }
}
