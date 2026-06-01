<?php

declare(strict_types=1);

namespace Parallite {

    use Closure;
    use Throwable;

    if (! function_exists('Parallite\async')) {
        /**
         * Create a promise for async execution with chainable then/catch/finally
         *
         * @template TReturn
         *
         * @param  Closure(): TReturn  $closure  Anonymous function to execute
         * @param  bool|null  $enableBenchmark  Enable benchmark (null = read from config)
         * @return Promise<TReturn>
         */
        function async(Closure $closure, ?bool $enableBenchmark = null): Promise
        {
            static $client = null;
            static $benchmarkClient = null;

            $shouldBenchmark = $enableBenchmark ?? getBenchmarkConfig();

            if ($shouldBenchmark) {
                if ($benchmarkClient === null) {
                    $benchmarkClient = new ParalliteClient(enableBenchmark: true);
                }

                return $benchmarkClient->promise($closure);
            }

            if ($client === null) {
                $client = new ParalliteClient;
            }

            return $client->promise($closure);
        }
    }

    if (! function_exists('Parallite\await')) {
        /**
         * Await a promise or array of promises to resolve
         *
         * @template TReturn
         *
         * @param  Promise<TReturn>|array<Promise<TReturn>>|array{pid: int, temp_file: string}  $promise  Single promise or array of promises
         * @return TReturn|array<TReturn> Single result or array of results (if was a valid promises on array)
         *
         * @throws Throwable
         */
        function await(Promise|array $promise): mixed
        {
            static $client = null;

            if ($client === null) {
                $client = new ParalliteClient;
            }

            if (is_array($promise) && ! isset($promise['pid'])) {
                return $client->awaitMultiple($promise);
            }

            return $client->await($promise);
        }
    }

    function getBenchmarkConfig(): bool
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $dir = __DIR__;
        for ($i = 0; $i < 10; $i++) {
            $configPath = $dir.'/parallite.json';
            if (file_exists($configPath)) {
                $json = file_get_contents($configPath);
                if ($json === false) {
                    continue;
                }
                $data = json_decode($json, true);
                if (! is_array($data)) {
                    continue;
                }
                $config = (bool) ($data['enable_benchmark'] ?? false);

                return $config;
            }

            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break;
            }
            $dir = $parentDir;
        }

        return false;
    }
}

namespace {
    use Parallite\Promise;

    if (! function_exists('async')) {
        /**
         * Global alias for Parallite\async()
         *
         * @template TReturn
         *
         * @param  Closure(): TReturn  $closure
         * @param  bool|null  $enableBenchmark  Enable benchmark (null = read from config)
         * @return Promise<TReturn>
         */
        function async(Closure $closure, ?bool $enableBenchmark = null): Promise
        {
            return \Parallite\async($closure, $enableBenchmark);
        }
    }

    if (! function_exists('await')) {
        /**
         * Global alias for Parallite\await()
         *
         * @template TReturn
         *
         * @param  Promise<TReturn>|array<Promise<TReturn>>|array{pid: int, temp_file: string}  $promise  Single promise or array of promises
         * @return TReturn|array<TReturn> Single result or array of results
         *
         * @throws Throwable
         */
        function await(Promise|array $promise): mixed
        {
            return \Parallite\await($promise);
        }
    }

    if (! function_exists('pd')) {
        /**
         * Parallite Dump debugging function
         *
         * @param  mixed  ...$values  Pass variables to dump pd($var1, $var2, ...)
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
                $dump .= "\n".str_repeat('=', 50)."\n\n";
                $dump .= preg_replace('/^[^\n]*\n/', '', $callerInfo);

                ob_start();
                var_dump($values);
                $output = ob_get_clean();

                $dump .= preg_replace('/^[^\n]*\n/', '', (string) $output);
                $dump .= "\n".str_repeat('=', 50)."\n\n";
            } else {
                $dump = $callerInfo.'[Nothing to dump]';
            }

            throw new RuntimeException($dump, 1);
        }
    }
}
