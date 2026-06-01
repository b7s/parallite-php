<?php

declare(strict_types=1);

namespace Parallite;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Promise wrapper for parallel task execution with chainable then/catch/finally
 *
 * @template TReturn
 */
final class Promise
{
    /**
     * @var array{pid: int, temp_file: string, benchmark?: array<string, mixed>}|null
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
        bool $eager = true,
    ) {
        if ($eager) {
            $this->start();
        }
    }

    /**
     * @return TReturn
     */
    public function __invoke(): mixed
    {
        return $this->resolve();
    }

    /**
     * Start the async execution if not already started
     *
     * @return array{pid: int, temp_file: string}
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
     * @return array{pid: int, temp_file: string}
     */
    public function getFuture(): array
    {
        return $this->start();
    }

    /**
     * Resolve the promise and apply all chained callbacks
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
            $futureRef = &$this->future;
            $result = $this->client->await($futureRef);

            if (isset($this->future['benchmark'])) {
                $this->benchmark = BenchmarkData::fromArray($this->future['benchmark']);
            }
        } catch (Throwable $e) {
            $exception = $e;
            $isError = true;
        }

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

        foreach ($this->handlers as $handler) {
            if ($handler['type'] === 'finally') {
                $handler['callback']();
            }
        }

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

    public function getBenchmark(): ?BenchmarkData
    {
        return $this->benchmark;
    }
}
