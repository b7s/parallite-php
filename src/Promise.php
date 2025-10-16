<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use Socket;
use Throwable;

/**
 * Promise wrapper for parallel task execution with chainable then/catch/finally
 * @template TReturn
 */
final class Promise
{
    /**
     * @var array{socket: Socket, task_id: string, benchmark?: array<string, mixed>}|null
     */
    private ?array $future = null;

    /**
     * @var array<Closure>
     */
    private array $thenCallbacks = [];

    /**
     * @var array<Closure>
     */
    private array $catchCallbacks = [];

    /**
     * @var array<Closure> $finallyCallbacks
     */
    private array $finallyCallbacks = [];

    /**
     * @var ?BenchmarkData $benchmark
     */
    private ?BenchmarkData $benchmark = null;

    /**
     * @param Closure(): TReturn $callback
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
     * @return mixed
     * @throws Throwable
     */
    public function resolve(): mixed
    {
        $this->start();

        try {
            // Pass future by reference to allow await() to modify it
            $futureRef = &$this->future;
            $result = $this->client->await($futureRef);

            // Extract benchmark data if available
            if (isset($this->future['benchmark'])) {
                $this->benchmark = BenchmarkData::fromArray($this->future['benchmark']);
            }

            // Apply all then callbacks in sequence
            foreach ($this->thenCallbacks as $then) {
                $result = $then($result);
            }

            return $result;
        } catch (Throwable $e) {
            // Apply catch callbacks
            foreach ($this->catchCallbacks as $catch) {
                try {
                    $result = $catch($e);
                    
                    // Apply remaining then callbacks after catch
                    foreach ($this->thenCallbacks as $then) {
                        $result = $then($result);
                    }
                    
                    return $result;
                } catch (Throwable $newException) {
                    $e = $newException;
                }
            }

            throw $e;
        } finally {
            // Apply finally callbacks
            foreach ($this->finallyCallbacks as $finally) {
                $finally();
            }
        }
    }

    /**
     * Add a then callback to the promise chain
     * 
     * @template TThenReturn
     * @param Closure(TReturn): TThenReturn $then
     * @return self<TThenReturn>
     */
    public function then(Closure $then): self
    {
        $this->thenCallbacks[] = $then;

        return $this;
    }

    /**
     * Add a catch callback to handle exceptions
     * 
     * @template TCatchReturn
     * @param Closure(Throwable): TCatchReturn $catch
     * @return self<TReturn|TCatchReturn>
     */
    public function catch(Closure $catch): self
    {
        $this->catchCallbacks[] = $catch;

        return $this;
    }

    /**
     * Add a final callback that runs regardless of success/failure
     * 
     * @param Closure(): void $finally
     * @return self<TReturn>
     */
    public function finally(Closure $finally): self
    {
        $this->finallyCallbacks[] = $finally;

        return $this;
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
     * Get benchmark data if available
     * 
     * Returns null if benchmark mode was not enabled or if promise hasn't been resolved yet.
     * 
     * @return BenchmarkData|null
     */
    public function getBenchmark(): ?BenchmarkData
    {
        return $this->benchmark;
    }
}
