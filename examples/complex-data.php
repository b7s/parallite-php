<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

echo "=== Complex Data Handling with Parallite ===\n\n";

// Example 1: Non-sequential keys (preserved!)
echo "1. Non-Sequential Integer Keys\n";
echo "--------------------------------\n";

try {
    $result1 = await(async(function () {
        return [
            'users' => [
                1 => ['name' => 'Alice', 'age' => 30],
                5 => ['name' => 'Bob', 'age' => 25],
                10 => ['name' => 'Charlie', 'age' => 35],
            ],
            'stats' => [
                'total' => 3,
                'average_age' => 30.0,
            ],
        ];
    }));
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n\n";
    exit(1);
}

echo 'Result: '.json_encode($result1, JSON_PRETTY_PRINT)."\n\n";

// Example 2: DateTime objects (automatically normalized)
echo "2. DateTime Objects\n";
echo "--------------------\n";

$result2 = await(async(function () {
    return [
        'created_at' => new DateTime('2024-01-15 10:30:00'),
        'updated_at' => new DateTime,
        'data' => ['status' => 'active'],
    ];
}));

echo 'Result: '.json_encode($result2, JSON_PRETTY_PRINT)."\n\n";

// Example 3: stdClass Objects
echo "3. stdClass Objects\n";
echo "--------------------\n";

$result3 = await(async(function () {
    $user1 = new stdClass;
    $user1->id = 1;
    $user1->name = 'Alice';
    $user1->email = 'alice@example.com';

    $user2 = new stdClass;
    $user2->id = 2;
    $user2->name = 'Bob';
    $user2->email = 'bob@example.com';

    return ['users' => [$user1, $user2]];
}));

echo 'Users: '.json_encode($result3, JSON_PRETTY_PRINT)."\n\n";

// Example 4: Mixed Key Types (Preserved!)
echo "4. Mixed Key Types\n";
echo "-------------------\n";

$result4 = await(async(function () {
    return [
        'data' => [
            0 => 'first',
            'name' => 'test',
            1 => 'second',
            'value' => 123,
            5 => 'fifth',
        ],
    ];
}));

echo 'Result: '.json_encode($result4, JSON_PRETTY_PRINT)."\n\n";

echo "=== All examples completed successfully! ===\n";
