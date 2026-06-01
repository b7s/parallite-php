<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('ParalliteClient', function () {
    it('can be instantiated with default settings', function () {
        $client = new ParalliteClient;

        expect($client)->toBeInstanceOf(ParalliteClient::class);
    });

    it('can be instantiated with benchmark enabled', function () {
        $client = new ParalliteClient(enableBenchmark: true);

        expect($client)->toBeInstanceOf(ParalliteClient::class)
            ->and($client->isBenchmarkEnabled())->toBeTrue();
    });

    it('reports fork mode availability', function () {
        $client = new ParalliteClient;

        expect($client->isForkMode())->toBeBool();
    });

    it('throws exception when awaiting null future', function () {
        $client = new ParalliteClient;

        $future = null;
        $client->await($future);
    })->throws(RuntimeException::class, 'No future provided');

    it('throws exception when awaiting empty future', function () {
        $client = new ParalliteClient;

        $future = [];
        $client->await($future);
    })->throws(RuntimeException::class, 'No future provided');
});

describe('ParalliteClient::async', function () {
    it('returns future with pid and temp_file structure', function () {
        $client = new ParalliteClient;

        $future = $client->async(fn () => 'test');

        expect($future)
            ->toBeArray()
            ->toHaveKeys(['pid', 'temp_file']);

        if ($client->isForkMode()) {
            expect($future['pid'])->toBeGreaterThan(0)
                ->and($future['temp_file'])->toBeString();
        }
    });

    it('generates unique pids for multiple tasks', function () {
        $client = new ParalliteClient;

        if (! $client->isForkMode()) {
            expect(true)->toBeTrue();

            return;
        }

        $future1 = $client->async(fn () => 'task1');
        $future2 = $client->async(fn () => 'task2');
        $future3 = $client->async(fn () => 'task3');

        expect($future1['pid'])
            ->not->toBe($future2['pid'])
            ->and($future2['pid'])->not->toBe($future3['pid'])
            ->and($future1['pid'])->not->toBe($future3['pid']);

        $client->await($future1);
        $client->await($future2);
        $client->await($future3);
    });
});

describe('ParalliteClient::awaitAll', function () {
    it('returns empty array for empty input', function () {
        $client = new ParalliteClient;

        $results = $client->awaitAll([]);

        expect($results)->toBe([]);
    });

    it('returns results in same order as input', function () {
        $client = new ParalliteClient;

        $results = $client->awaitAll([
            fn () => 'first',
            fn () => 'second',
            fn () => 'third',
        ]);

        expect($results)
            ->toBe(['first', 'second', 'third']);
    });

    it('handles numeric results correctly', function () {
        $client = new ParalliteClient;

        $results = $client->awaitAll([
            fn () => 1,
            fn () => 2,
            fn () => 3,
        ]);

        expect($results)
            ->toBe([1, 2, 3]);
    });
});
