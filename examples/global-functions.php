<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Sem necessidade de "use function" - funções globais!

echo "=== Global Functions (No Import Required) ===\n\n";

// Exemplo 1: Uso básico
echo "1. Basic usage:\n";
$result = await(async(fn () => 'Hello World'));
echo "Result: {$result}\n\n";

// Exemplo 2: Com chaining
echo "2. With chaining:\n";
$result = await(
    async(fn () => 10)
        ->then(fn ($n) => $n * 2)
        ->then(fn ($n) => $n + 5)
);
echo "Result: {$result}\n\n"; // 25

// Exemplo 3: Múltiplas promises em paralelo
echo "3. Multiple promises in parallel:\n";
$start = microtime(true);

$p1 = async(function () {
    sleep(1);

    return 'Task 1';
});
$p2 = async(function () {
    sleep(1);

    return 'Task 2';
});
$p3 = async(function () {
    sleep(1);

    return 'Task 3';
});

$results = [
    await($p1),
    await($p2),
    await($p3),
];

$duration = microtime(true) - $start;
echo 'Results: '.implode(', ', $results)."\n";
echo 'Duration: '.round($duration, 2)."s (parallel!)\n\n";

// Exemplo 4: Error handling
echo "4. Error handling:\n";
$result = await(
    async(function () {
        throw new Exception('Oops!');
    })->catch(fn ($e) => 'Caught: '.$e->getMessage())
);
echo "Result: {$result}\n\n";

// Exemplo 5: Complex transformation
echo "5. Complex transformation:\n";
$result = await(
    async(fn () => ['name' => 'john', 'age' => 25])
        ->then(fn ($data) => array_merge($data, ['name' => strtoupper($data['name'])]))
        ->then(fn ($data) => array_merge($data, ['age' => $data['age'] + 5]))
        ->then(fn ($data) => json_encode($data))
);
echo "Result: {$result}\n\n";

echo "=== Done! No 'use function' needed! ===\n";
