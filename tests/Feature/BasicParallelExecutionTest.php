<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('Basic Parallel Execution', function () {
    beforeEach(function () {
        $this->client = new ParalliteClient(autoManageDaemon: true);
    });

    afterEach(function () {
        $this->client->stopDaemon();
    });

    it('executes tasks in parallel faster than sequential', function () {
        $start = microtime(true);

        $future1 = $this->client->async(function () {
            usleep(100000); // 100ms

            return 'Task 1 completed';
        });

        $future2 = $this->client->async(function () {
            usleep(100000); // 100ms

            return 'Task 2 completed';
        });

        $future3 = $this->client->async(function () {
            usleep(100000); // 100ms

            return 'Task 3 completed';
        });

        $result1 = $this->client->await($future1);
        $result2 = $this->client->await($future2);
        $result3 = $this->client->await($future3);

        $duration = microtime(true) - $start;

        expect($result1)->toBe('Task 1 completed')
            ->and($result2)->toBe('Task 2 completed')
            ->and($result3)->toBe('Task 3 completed')
            ->and($duration)->toBeLessThan(0.25); // Should be ~0.1s, not 0.3s
    });

    it('uses awaitAll convenience method correctly', function () {
        $start = microtime(true);

        $results = $this->client->awaitAll([
            function () {
                usleep(100000);

                return 'Task A';
            },
            function () {
                usleep(100000);

                return 'Task B';
            },
            function () {
                usleep(100000);

                return 'Task C';
            },
        ]);

        $duration = microtime(true) - $start;

        expect($results)
            ->toBe(['Task A', 'Task B', 'Task C'])
            ->and($duration)->toBeLessThan(0.25);
    });

    it('works with data processing', function () {
        $numbers = [1, 2, 3, 4, 5];
        $futures = [];

        foreach ($numbers as $n) {
            $futures[] = $this->client->async(function () use ($n) {
                usleep(10000); // 10ms

                return $n * $n;
            });
        }

        $squares = [];
        foreach ($futures as $future) {
            $squares[] = $this->client->await($future);
        }

        expect($squares)->toBe([1, 4, 9, 16, 25]);
    });

    it('handles errors correctly', function () {
        $errorFuture = $this->client->async(function () {
            throw new RuntimeException('Simulated error');
        });

        expect(fn () => $this->client->await($errorFuture))
            ->toThrow(RuntimeException::class, 'Simulated error');
    });

    it('returns correct result types', function () {
        $results = $this->client->awaitAll([
            fn () => 42,
            fn () => 'string result',
            fn () => [1, 2, 3],
            fn () => true,
            fn () => null,
        ]);

        expect($results[0])->toBe(42)
            ->and($results[1])->toBe('string result')
            ->and($results[2])->toBe([1, 2, 3])
            ->and($results[3])->toBe(true)
            ->and($results[4])->toBe(null);
    });

    it('handles closures with use variables', function () {
        $multiplier = 10;
        $offset = 5;

        $results = $this->client->awaitAll([
            fn () => 1 * $multiplier + $offset,
            fn () => 2 * $multiplier + $offset,
            fn () => 3 * $multiplier + $offset,
        ]);

        expect($results)->toBe([15, 25, 35]);
    });
});
