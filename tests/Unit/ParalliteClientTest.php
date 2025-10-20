<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('ParalliteClient', function () {
    it('can be instantiated with default socket path', function () {
        $client = new ParalliteClient;

        expect($client)->toBeInstanceOf(ParalliteClient::class);
    });

    it('can be instantiated with custom socket path', function () {
        $socketPath = '/tmp/test-parallite.sock';
        $client = new ParalliteClient($socketPath);

        expect($client)->toBeInstanceOf(ParalliteClient::class);
    });

    it('returns correct default socket path for unix systems', function () {
        $socketPath = ParalliteClient::getDefaultSocketPath();

        expect($socketPath)
            ->toBeString()
            ->toContain('parallite_');
    });

    it('throws exception when connecting to non-existent daemon', function () {
        $exceptionThrown = false;
        $exceptionMessage = '';

        try {
            $client = new ParalliteClient('/tmp/non-existent-parallite.sock', autoManageDaemon: false);
            $client->async(fn () => 'test');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }

        expect($exceptionThrown)->toBeTrue()
            ->and($exceptionMessage)->toContain('Failed to connect to daemon');
    });

    it('throws exception when awaiting null future', function () {
        $client = new ParalliteClient;

        $future = null;
        $client->await($future);
    })->throws(RuntimeException::class, 'No future or socket provided');

    it('throws exception when awaiting empty future', function () {
        $client = new ParalliteClient;

        $future = [];
        $client->await($future);
    })->throws(RuntimeException::class, 'No future or socket provided');
});

describe('ParalliteClient::async', function () {
    it('returns future with socket and task_id structure', function () {
        $client = new ParalliteClient(autoManageDaemon: true);

        $future = $client->async(fn () => 'test');

        expect($future)
            ->toBeArray()
            ->toHaveKeys(['socket', 'task_id'])
            ->and($future['task_id'])->toBeString()
            ->and($future['socket'])->not->toBeNull();

        $client->stopDaemon();
    });

    it('generates unique task ids for multiple tasks', function () {
        $client = new ParalliteClient(autoManageDaemon: true);

        $future1 = $client->async(fn () => 'task1');
        $future2 = $client->async(fn () => 'task2');
        $future3 = $client->async(fn () => 'task3');

        expect($future1['task_id'])
            ->not->toBe($future2['task_id'])
            ->and($future2['task_id'])->not->toBe($future3['task_id'])
            ->and($future1['task_id'])->not->toBe($future3['task_id']);

        $client->await($future1);
        $client->await($future2);
        $client->await($future3);
        $client->stopDaemon();
    });
});

describe('ParalliteClient::awaitAll', function () {
    it('returns empty array for empty input', function () {
        $client = new ParalliteClient(autoManageDaemon: true);

        $results = $client->awaitAll([]);

        expect($results)->toBe([]);

        $client->stopDaemon();
    });

    it('returns results in same order as input', function () {
        $client = new ParalliteClient(autoManageDaemon: true);

        $results = $client->awaitAll([
            fn () => 'first',
            fn () => 'second',
            fn () => 'third',
        ]);

        expect($results)
            ->toBe(['first', 'second', 'third']);

        $client->stopDaemon();
    });

    it('handles numeric results correctly', function () {
        $client = new ParalliteClient(autoManageDaemon: true);

        $results = $client->awaitAll([
            fn () => 1,
            fn () => 2,
            fn () => 3,
        ]);

        expect($results)
            ->toBe([1, 2, 3]);

        $client->stopDaemon();
    });
});
