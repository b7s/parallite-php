<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

use function Parallite\async;
use function Parallite\await;

describe('Promise Chaining', function () {
    beforeEach(function () {
        $this->client = new ParalliteClient(autoManageDaemon: true);
    });

    afterEach(function () {
        $this->client->stopDaemon();
    });

    it('chains then callbacks correctly', function () {
        $promise = $this->client->promise(fn (): int => 1 + 2)
            ->then(fn ($result): int => $result + 2)
            ->then(fn ($result): int => $result * 2);

        $result = $this->client->await($promise);

        expect($result)->toBe(10); // (1+2+2)*2 = 10
    });

    it('handles catch for exceptions', function () {
        $promise = $this->client->promise(function () {
            throw new Exception('Error');
        })->catch(function (Throwable $e) {
            return 'Rescued: '.$e->getMessage();
        });

        $result = $this->client->await($promise);

        expect($result)->toBe('Rescued: Task failed (awaitTask): Error');
    });

    it('chains then after catch', function () {
        $promise = $this->client->promise(function () {
            throw new Exception('Error');
        })
            ->catch(fn (Throwable $e) => 'rescued')
            ->then(fn ($result) => strtoupper($result));

        $result = $this->client->await($promise);

        expect($result)->toBe('RESCUED');
    });

    it('executes finally callback on success', function () {
        $finallyCalled = false;

        $promise = $this->client->promise(fn () => 'success')
            ->finally(function () use (&$finallyCalled) {
                $finallyCalled = true;
            });

        $result = $this->client->await($promise);

        expect($result)->toBe('success')
            ->and($finallyCalled)->toBeTrue();
    });

    it('executes finally callback on failure', function () {
        $finallyCalled = false;

        $promise = $this->client->promise(function () {
            throw new Exception('Error');
        })
            ->catch(fn () => 'caught')
            ->finally(function () use (&$finallyCalled) {
                $finallyCalled = true;
            });

        $result = $this->client->await($promise);

        expect($result)->toBe('caught')
            ->and($finallyCalled)->toBeTrue();
    });

    it('chains multiple then and catch callbacks', function () {
        $promise = $this->client->promise(fn () => 10)
            ->then(fn ($n) => $n * 2)
            ->then(fn ($n) => $n + 5)
            ->catch(fn () => 'should not be called')
            ->then(fn ($n) => $n / 5);

        $result = $this->client->await($promise);

        expect($result)->toBe(5); // ((10*2)+5)/5 = 5
    });

    it('preserves parallel execution with promises', function () {
        $start = microtime(true);

        $promise1 = $this->client->promise(function () {
            usleep(100000); // 100ms

            return 'Task 1';
        })->then(fn ($r) => $r.' completed');

        $promise2 = $this->client->promise(function () {
            usleep(100000); // 100ms

            return 'Task 2';
        })->then(fn ($r) => $r.' completed');

        $promise3 = $this->client->promise(function () {
            usleep(100000); // 100ms

            return 'Task 3';
        })->then(fn ($r) => $r.' completed');

        $result1 = $this->client->await($promise1);
        $result2 = $this->client->await($promise2);
        $result3 = $this->client->await($promise3);

        $duration = microtime(true) - $start;

        expect($result1)->toBe('Task 1 completed')
            ->and($result2)->toBe('Task 2 completed')
            ->and($result3)->toBe('Task 3 completed')
            ->and($duration)->toBeLessThan(0.35); // Should be ~0.1s, not 0.3s (with overhead)
    });

    it('can invoke promise directly', function () {
        $promise = $this->client->promise(fn () => 42)
            ->then(fn ($n) => $n * 2);

        $result = $promise();

        expect($result)->toBe(84);
    });

    // TODO: Implement validation to prevent chaining after promise started
    // it('throws when chaining after promise started', function () {
    //     $promise = $this->client->promise(fn () => 42);
    //
    //     // Start the promise
    //     $promise->start();
    //
    //     expect(fn () => $promise->then(fn ($n) => $n * 2))
    //         ->toThrow(RuntimeException::class, 'Cannot chain then() after promise has started');
    // });
});

describe('Helper Functions', function () {
    it('works with async helper function', function () {
        $promise = async(fn (): int => 1 + 2)
            ->then(fn ($result): int => $result + 2)
            ->then(fn ($result): int => $result * 2);

        $result = await($promise);

        expect($result)->toBe(10);
    });

    it('handles catch with async helper', function () {
        $promise = async(function () {
            throw new Exception('Error');
        })->catch(function (Throwable $e) {
            return 'Rescued: '.$e->getMessage();
        });

        $result = await($promise);

        expect($result)->toBe('Rescued: Task failed (awaitTask): Error');
    });

    it('chains complex operations with helpers', function () {
        $promise = async(fn () => 5)
            ->then(fn ($n) => $n * 3)
            ->then(fn ($n) => $n + 10)
            ->then(fn ($n) => $n / 5);

        $result = await($promise);

        expect($result)->toBe(5); // ((5*3)+10)/5 = 5
    });

    it('executes multiple promises in parallel with helpers', function () {
        $start = microtime(true);

        $p1 = async(function () {
            usleep(100000);

            return 'A';
        });
        $p2 = async(function () {
            usleep(100000);

            return 'B';
        });
        $p3 = async(function () {
            usleep(100000);

            return 'C';
        });

        $results = [
            await($p1),
            await($p2),
            await($p3),
        ];

        $duration = microtime(true) - $start;

        expect($results)->toBe(['A', 'B', 'C'])
            ->and($duration)->toBeLessThan(0.35);
    });

    it('supports multiple then callbacks in sequence', function () {
        $promise = async(fn (): int => 2)
            ->then(fn (int $value): int => $value + 3)
            ->then(fn (int $value): int => $value * 4)
            ->then(fn (int $value): int => $value - 5);

        $result = await($promise);

        expect($result)->toBe(15); // (((2 + 3) * 4) - 5) = 15
    });

    it('continues then chaining after catch recovers from error', function () {
        $calls = [];

        $promise = async(function (): int {
            throw new RuntimeException('broken');
        })
            ->then(function (int $value) use (&$calls): int {
                $calls[] = 'then-before-catch';

                return $value;
            })
            ->catch(function (Throwable $exception) use (&$calls): int {
                $calls[] = 'caught: '.$exception->getMessage();

                return 7;
            })
            ->then(function (int $value) use (&$calls): int {
                $calls[] = 'then-after-catch';

                return $value * 3;
            });

        $result = await($promise);

        expect($result)->toBe(21)
            ->and($calls)->toBe([
                'caught: Task failed (awaitTask): broken',
                'then-after-catch',
            ]);
    });

    it('executes finally callbacks regardless of success or failure', function () {
        $log = [];

        $success = async(fn (): string => 'ok')
            ->finally(function () use (&$log): void {
                $log[] = 'success-finally';
            });

        $failure = async(function (): string {
            throw new RuntimeException('boom');
        })
            ->catch(fn (): string => 'handled')
            ->finally(function () use (&$log): void {
                $log[] = 'failure-finally';
            });

        expect(await($success))->toBe('ok');
        expect(await($failure))->toBe('handled')
            ->and($log)->toBe([
                'success-finally',
                'failure-finally',
            ]);
    });
});
