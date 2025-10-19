<?php

declare(strict_types=1);

namespace Parallite {

    use Closure;
    use Socket;
    use Throwable;

    /**
     * Read benchmark configuration from parallite.json
     *
     * @return bool
     */
    function getBenchmarkConfig(): bool
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        // Find project root and config file
        $dir = __DIR__;
        for ($i = 0; $i < 10; $i++) {
            $configPath = $dir . '/parallite.json';
            if (file_exists($configPath)) {
                $json = file_get_contents($configPath);
                if ($json === false) {
                    continue;
                }
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    continue;
                }
                $config = (bool)($data['enable_benchmark'] ?? false);
                return $config;
            }

            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break;
            }
            $dir = $parentDir;
        }

        // Default to false if no config found
        $config = false;
        return $config;
    }

    if (!function_exists('Parallite\async')) {
        /**
         * Create a promise for async execution with chainable then/catch/finally
         * See more: https://promisesaplus.com/
         *
         * @template TReturn
         * @param Closure(): TReturn $closure         Anonymous function to execute
         * @param bool|null          $enableBenchmark Enable benchmark (null = read from config)
         * @return Promise<TReturn>
         */
        function async(Closure $closure, ?bool $enableBenchmark = null): Promise
        {
            static $client = null;
            static $benchmarkClient = null;

            // Determine if benchmark should be enabled
            // Priority: parameter > config file > default (false)
            $shouldBenchmark = $enableBenchmark ?? getBenchmarkConfig();

            // Use benchmark client if needed
            if ($shouldBenchmark) {
                if ($benchmarkClient === null) {
                    $benchmarkClient = new ParalliteClient(
                        autoManageDaemon: true,
                        enableBenchmark: true
                    );

                    // Register shutdown to stop daemon
                    register_shutdown_function(static function () use ($benchmarkClient): void {
                        $benchmarkClient->stopDaemon();
                    });
                }

                return $benchmarkClient->promise($closure);
            }

            // Use regular client
            if ($client === null) {
                $client = new ParalliteClient(autoManageDaemon: true);

                // Register shutdown to stop daemon
                register_shutdown_function(static function () use ($client): void {
                    $client->stopDaemon();
                });
            }

            return $client->promise($closure);
        }
    }

    if (!function_exists('Parallite\await')) {
        /**
         * Await a promise or array of promises to resolve
         *
         * @template TReturn
         * @param Promise<TReturn>|array<Promise<TReturn>>|array{socket: Socket, task_id: string} $promise Single promise or array of promises
         * @return TReturn|array<TReturn> Single result or array of results (if was a valid promises on array)
         *
         */
        function await(Promise|array $promise): mixed
        {
            static $client = null;

            if ($client === null) {
                $client = new ParalliteClient(autoManageDaemon: true);
            }

            if (is_array($promise) && !isset($promise['socket'])) {
                return $client->awaitMultiple($promise);
            }

            // Single promise or future
            return $client->await($promise);
        }
    }
}

namespace {
    use Parallite\Promise;

    // Global aliases for convenience - use async() and await() without namespace imports
    if (!function_exists('async')) {
        /**
         * Global alias for Parallite\async()
         *
         * @template TReturn
         * @param Closure(): TReturn $closure
         * @param bool|null          $enableBenchmark Enable benchmark (null = read from config)
         * @return Promise<TReturn>
         */
        function async(Closure $closure, ?bool $enableBenchmark = null): Promise
        {
            return \Parallite\async($closure, $enableBenchmark);
        }
    }

    if (!function_exists('await')) {
        /**
         * Global alias for Parallite\await()
         *
         * @template TReturn
         * @param Promise<TReturn>|array<Promise<TReturn>>|array{socket: Socket, task_id: string} $promise Single promise or array of promises
         * @return TReturn|array<TReturn> Single result or array of results
         * @throws Throwable
         */
        function await(Promise|array $promise): mixed
        {
            return \Parallite\await($promise);
        }
    }

    if (!function_exists('pd')) {
        /**
         * Parallite Dump debugging function
         *
         * Dump the passed variables and end the script inside async calls.
         *
         * @param mixed ...$values Pass variables to dump pd($var1, $var2, ...)
         * @return void
         */
        function pd(mixed ...$values): void
        {
            $dump = '';

            $backtrace = debug_backtrace(limit: 1);
            $caller = $backtrace[0];

            $callerInfo = sprintf(
                "\nCalled from %s:%d\n%s%s%s\n\n",
                $caller['file'] ?? 'unknown file',
                $caller['line'] ?? 0,
                $caller['class'] ?? '',
                $caller['type'] ?? '',
                $caller['function']
            );

            if (count($values) > 0) {
                $dump .= "\n" . str_repeat('=', 50) . "\n\n";
                $dump .= preg_replace('/^[^\n]*\n/', '', $callerInfo);

                ob_start();
                var_dump($values);
                $output = ob_get_clean();

                $dump .= preg_replace('/^[^\n]*\n/', '', (string) $output);
                $dump .= "\n" . str_repeat('=', 50) . "\n\n";
            } else {
                $dump = $callerInfo . '[Nothing to dump]';
            }

            throw new \Exception($dump, 1);
        }
    }
}
