<?php

declare(strict_types=1);

test('awaits array of promises and returns array of results', function () {
    $promises = [
        async(fn () => 'Task 1'),
        async(fn () => 'Task 2'),
        async(fn () => 'Task 3'),
    ];

    $results = await($promises);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(3)
        ->and($results[0])->toBe('Task 1')
        ->and($results[1])->toBe('Task 2')
        ->and($results[2])->toBe('Task 3');
});

test('awaits associative array of promises preserving keys', function () {
    $promises = [
        'first' => async(fn () => 'Result 1'),
        'second' => async(fn () => 'Result 2'),
        'third' => async(fn () => 'Result 3'),
    ];

    $results = await($promises);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(3)
        ->and($results)->toHaveKey('first')
        ->and($results)->toHaveKey('second')
        ->and($results)->toHaveKey('third')
        ->and($results['first'])->toBe('Result 1')
        ->and($results['second'])->toBe('Result 2')
        ->and($results['third'])->toBe('Result 3');
});

test('awaits mixed array with promises and static values', function () {
    $mixed = [
        'static' => 'Static value',
        'promise1' => async(fn () => 'Promise 1'),
        'number' => 42,
        'promise2' => async(fn () => 'Promise 2'),
        'array' => ['nested' => 'data'],
    ];

    $results = await($mixed);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(5)
        ->and($results['static'])->toBe('Static value')
        ->and($results['promise1'])->toBe('Promise 1')
        ->and($results['number'])->toBe(42)
        ->and($results['promise2'])->toBe('Promise 2')
        ->and($results['array'])->toBe(['nested' => 'data']);
});

test('awaits array with only static values', function () {
    $static = [
        'a' => 'value A',
        'b' => 'value B',
        'c' => 123,
    ];

    $results = await($static);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(3)
        ->and($results['a'])->toBe('value A')
        ->and($results['b'])->toBe('value B')
        ->and($results['c'])->toBe(123);
});

test('awaits empty array', function () {
    $results = await([]);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(0);
});

test('awaits array of promises with different return types', function () {
    $promises = [
        async(fn () => 42),
        async(fn () => 'Hello'),
        async(fn () => ['key' => 'value']),
        async(fn () => true),
        async(fn () => null),
    ];

    $results = await($promises);

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(5)
        ->and($results[0])->toBe(42)
        ->and($results[1])->toBe('Hello')
        ->and($results[2])->toBe(['key' => 'value'])
        ->and($results[3])->toBe(true)
        ->and($results[4])->toBe(null);
});

test('executes promises in parallel when using array', function () {
    $start = microtime(true);

    $promises = [
        async(function () {
            usleep(100000); // 100ms

            return 'Task 1';
        }),
        async(function () {
            usleep(100000); // 100ms

            return 'Task 2';
        }),
        async(function () {
            usleep(100000); // 100ms

            return 'Task 3';
        }),
    ];

    $results = await($promises);
    $duration = microtime(true) - $start;

    expect($results)->toHaveCount(3)
        ->and($results[0])->toBe('Task 1')
        ->and($results[1])->toBe('Task 2')
        ->and($results[2])->toBe('Task 3')
        ->and($duration)->toBeLessThan(0.25); // Should be ~0.1s, not 0.3s (parallel)
});

test('handles promises with transformations in array', function () {
    $promises = [
        async(fn () => 10)->then(fn ($n) => $n * 2),
        async(fn () => 'hello')->then(fn ($s) => strtoupper($s)),
        async(fn () => [1, 2, 3])->then(fn ($arr) => array_sum($arr)),
    ];

    $results = await($promises);

    expect($results)->toBeArray()
        ->and($results[0])->toBe(20)
        ->and($results[1])->toBe('HELLO')
        ->and($results[2])->toBe(6);
});

test('preserves array keys with numeric indices', function () {
    $promises = [
        10 => async(fn () => 'Ten'),
        20 => async(fn () => 'Twenty'),
        30 => async(fn () => 'Thirty'),
    ];

    $results = await($promises);

    expect($results)->toBeArray()
        ->and($results)->toHaveKey(10)
        ->and($results)->toHaveKey(20)
        ->and($results)->toHaveKey(30)
        ->and($results[10])->toBe('Ten')
        ->and($results[20])->toBe('Twenty')
        ->and($results[30])->toBe('Thirty');
});

test('works with single promise (backward compatibility)', function () {
    $promise = async(fn () => 'Single result');
    $result = await($promise);

    expect($result)->toBe('Single result');
});
