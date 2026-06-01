<?php

declare(strict_types=1);

use Parallite\ParalliteClient;

describe('Real World Data Processing', function () {
    it('fetches and processes JSON data from public API in parallel', function () {
        $client = new ParalliteClient(enableBenchmark: true);

        echo "\nReal World Data Processing Test\n";
        echo "======================================================================\n\n";

        echo "Step 1: Fetching user data from API...\n";
        $usersPromise = $client->promise(function () {
            $url = 'https://jsonplaceholder.typicode.com/users';
            $json = file_get_contents($url);
            if ($json === false) {
                throw new RuntimeException('Failed to fetch users');
            }

            return json_decode($json, true);
        });

        echo "Step 2: Fetching posts data from API...\n";
        $postsPromise = $client->promise(function () {
            $url = 'https://jsonplaceholder.typicode.com/posts';
            $json = file_get_contents($url);
            if ($json === false) {
                throw new RuntimeException('Failed to fetch posts');
            }

            return json_decode($json, true);
        });

        echo "Step 3: Fetching comments data from API...\n";
        $commentsPromise = $client->promise(function () {
            $url = 'https://jsonplaceholder.typicode.com/comments';
            $json = file_get_contents($url);
            if ($json === false) {
                throw new RuntimeException('Failed to fetch comments');
            }

            return json_decode($json, true);
        });

        echo "Waiting for all API calls to complete...\n\n";
        $results = $client->awaitMultiple([$usersPromise, $postsPromise, $commentsPromise]);

        [$users, $posts, $comments] = $results;

        echo "Data fetched successfully!\n";
        echo '  Users: '.count($users)."\n";
        echo '  Posts: '.count($posts)."\n";
        echo '  Comments: '.count($comments)."\n\n";

        echo "Step 4: Processing user analytics in parallel...\n";

        $analyticsPromises = [];
        foreach ($users as $user) {
            $analyticsPromises[] = $client->promise(function () use ($user, $posts, $comments) {
                $userId = $user['id'];

                $userPosts = array_filter($posts, fn ($post) => $post['userId'] === $userId);

                $postIds = array_column($userPosts, 'id');
                $userComments = array_filter($comments, fn ($comment) => in_array($comment['postId'], $postIds));

                $totalWords = 0;
                foreach ($userPosts as $post) {
                    $totalWords += str_word_count($post['title'] ?? '');
                    $totalWords += str_word_count($post['body'] ?? '');
                }

                $avgCommentsPerPost = count($userPosts) > 0
                    ? count($userComments) / count($userPosts)
                    : 0;

                return [
                    'user_id' => $userId,
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'company' => $user['company']['name'] ?? 'N/A',
                    'city' => $user['address']['city'] ?? 'N/A',
                    'posts_count' => count($userPosts),
                    'comments_received' => count($userComments),
                    'total_words' => $totalWords,
                    'avg_comments_per_post' => round($avgCommentsPerPost, 2),
                    'engagement_score' => round(count($userComments) * 0.5 + count($userPosts) * 2, 2),
                ];
            });
        }

        echo 'Processing '.count($analyticsPromises)." users...\n";
        $analytics = $client->awaitMultiple($analyticsPromises);

        echo "Analytics completed!\n\n";

        echo "SUMMARY STATISTICS\n";
        echo "======================================================================\n\n";

        $totalPosts = array_sum(array_column($analytics, 'posts_count'));
        $totalComments = array_sum(array_column($analytics, 'comments_received'));
        $totalWords = array_sum(array_column($analytics, 'total_words'));
        $avgPostsPerUser = round($totalPosts / count($analytics), 2);
        $avgCommentsPerUser = round($totalComments / count($analytics), 2);
        $avgWordsPerUser = round($totalWords / count($analytics), 2);

        echo "Overall Metrics:\n";
        echo '  Total Users: '.count($analytics)."\n";
        echo "  Total Posts: {$totalPosts}\n";
        echo "  Total Comments: {$totalComments}\n";
        echo "  Total Words: {$totalWords}\n";
        echo "  Avg Posts/User: {$avgPostsPerUser}\n";
        echo "  Avg Comments/User: {$avgCommentsPerUser}\n";
        echo "  Avg Words/User: {$avgWordsPerUser}\n\n";

        usort($analytics, fn ($a, $b) => $b['engagement_score'] <=> $a['engagement_score']);

        echo "Top 5 Most Engaged Users:\n";
        echo "======================================================================\n";
        for ($i = 0; $i < min(5, count($analytics)); $i++) {
            $user = $analytics[$i];
            echo sprintf(
                "%d. %-20s | Posts: %2d | Comments: %3d | Score: %.2f\n",
                $i + 1,
                $user['username'],
                $user['posts_count'],
                $user['comments_received'],
                $user['engagement_score']
            );
        }
        echo "\n";

        $benchmarks = array_filter(array_map(fn ($p) => $p->getBenchmark(), $analyticsPromises));

        if (count($benchmarks) > 0) {
            $avgExecTime = round(array_sum(array_column($benchmarks, 'executionTimeMs')) / count($benchmarks), 2);
            $avgMemory = round(array_sum(array_column($benchmarks, 'memoryPeakMb')) / count($benchmarks), 2);
            $totalExecTime = round(array_sum(array_column($benchmarks, 'executionTimeMs')) / 1000, 2);

            echo "Performance Metrics:\n";
            echo "======================================================================\n";
            echo "  Avg Execution Time: {$avgExecTime}ms per task\n";
            echo "  Avg Memory Peak: {$avgMemory}MB per task\n";
            echo "  Total Processing Time: {$totalExecTime}s\n";
            echo '  Tasks Processed: '.count($benchmarks)."\n\n";
        }

        expect($analytics)->toHaveCount(count($users))
            ->and($totalPosts)->toBeGreaterThan(0)
            ->and($totalComments)->toBeGreaterThan(0)
            ->and($avgPostsPerUser)->toBeGreaterThan(0);

        foreach ($analytics as $userAnalytics) {
            expect($userAnalytics)->toHaveKeys([
                'user_id',
                'username',
                'name',
                'email',
                'company',
                'city',
                'posts_count',
                'comments_received',
                'total_words',
                'avg_comments_per_post',
                'engagement_score',
            ]);
        }

        echo "Real world data processing test passed!\n";
        echo "======================================================================\n\n";
    });

    it('processes large dataset with chunked parallel execution', function () {
        $client = new ParalliteClient(enableBenchmark: true);

        echo "\nChunked Data Processing Test\n";
        echo "======================================================================\n\n";

        $tempDir = sys_get_temp_dir().'/parallite_test_' . str_replace('.', '_', uniqid('', true));
        mkdir($tempDir, 0777, true);
        echo "Created temp directory: {$tempDir}\n\n";

        try {
            echo "Fetching multiple datasets in parallel...\n";

            $todosPromise = $client->promise(function () {
                $url = 'https://jsonplaceholder.typicode.com/todos';
                $json = file_get_contents($url);
                if ($json === false) {
                    throw new RuntimeException('Failed to fetch todos');
                }

                return json_decode($json, true);
            });

            $albumsPromise = $client->promise(function () {
                $url = 'https://jsonplaceholder.typicode.com/albums';
                $json = file_get_contents($url);
                if ($json === false) {
                    throw new RuntimeException('Failed to fetch albums');
                }

                return json_decode($json, true);
            });

            $photosPromise = $client->promise(function () {
                $url = 'https://jsonplaceholder.typicode.com/photos?_limit=100';
                $json = file_get_contents($url);
                if ($json === false) {
                    throw new RuntimeException('Failed to fetch photos');
                }

                return json_decode($json, true);
            });

            [$todos, $albums, $photos] = $client->awaitMultiple([$todosPromise, $albumsPromise, $photosPromise]);

            echo "Fetched datasets:\n";
            echo '  Todos: '.count($todos)."\n";
            echo '  Albums: '.count($albums)."\n";
            echo '  Photos: '.count($photos)."\n\n";

            echo "Processing photos and saving metadata to files...\n";

            $photoProcessPromises = [];
            foreach ($photos as $photo) {
                $photoProcessPromises[] = $client->promise(function () use ($photo, $tempDir) {
                    $albumId = $photo['albumId'];
                    $photoId = $photo['id'];

                    $albumDir = $tempDir.'/album_'.$albumId;
                    if (! is_dir($albumDir)) {
                        mkdir($albumDir, 0777, true);
                    }

                    $filename = $albumDir.'/photo_'.$photoId.'.json';
                    $metadata = [
                        'id' => $photo['id'],
                        'title' => $photo['title'],
                        'url' => $photo['url'],
                        'thumbnailUrl' => $photo['thumbnailUrl'],
                        'albumId' => $albumId,
                        'processed_at' => date('Y-m-d H:i:s'),
                        'title_length' => strlen($photo['title']),
                    ];

                    file_put_contents($filename, json_encode($metadata, JSON_PRETTY_PRINT));

                    return [
                        'photo_id' => $photoId,
                        'album_id' => $albumId,
                        'file' => $filename,
                        'title_length' => strlen($photo['title']),
                    ];
                });
            }

            $photoResults = $client->awaitMultiple($photoProcessPromises);
            echo 'Processed '.count($photoResults)." photos and saved to files\n\n";

            $chunkSize = 50;
            $chunks = array_chunk($todos, $chunkSize);

            echo 'Processing todos in '.count($chunks)." chunks...\n";

            $chunkPromises = [];
            foreach ($chunks as $index => $chunk) {
                $chunkPromises[] = $client->promise(function () use ($chunk, $index, $tempDir) {
                    $completed = 0;
                    $pending = 0;
                    $userStats = [];

                    foreach ($chunk as $todo) {
                        if ($todo['completed']) {
                            $completed++;
                        } else {
                            $pending++;
                        }

                        $userId = $todo['userId'];
                        if (! isset($userStats[$userId])) {
                            $userStats[$userId] = ['completed' => 0, 'pending' => 0];
                        }

                        if ($todo['completed']) {
                            $userStats[$userId]['completed']++;
                        } else {
                            $userStats[$userId]['pending']++;
                        }
                    }

                    $chunkFile = $tempDir.'/chunk_'.$index.'_summary.json';
                    file_put_contents($chunkFile, json_encode([
                        'chunk_index' => $index,
                        'completed' => $completed,
                        'pending' => $pending,
                        'user_stats' => $userStats,
                    ], JSON_PRETTY_PRINT));

                    return [
                        'chunk_index' => $index,
                        'items_processed' => count($chunk),
                        'completed' => $completed,
                        'pending' => $pending,
                        'completion_rate' => round(($completed / count($chunk)) * 100, 2),
                        'unique_users' => count($userStats),
                        'user_stats' => $userStats,
                        'file' => $chunkFile,
                    ];
                });
            }

            $chunkResults = $client->awaitMultiple($chunkPromises);
            echo "All chunks processed and saved!\n\n";

            $filesCreated = count($photoResults) + count($chunkResults);
            echo "Files created: {$filesCreated}\n";
            echo '  Photo metadata files: '.count($photoResults)."\n";
            echo '  Chunk summary files: '.count($chunkResults)."\n\n";

            $totalCompleted = array_sum(array_column($chunkResults, 'completed'));
            $totalPending = array_sum(array_column($chunkResults, 'pending'));
            $totalItems = $totalCompleted + $totalPending;
            $avgCompletionRate = round(array_sum(array_column($chunkResults, 'completion_rate')) / count($chunkResults), 2);

            $allUserStats = [];
            foreach ($chunkResults as $result) {
                foreach ($result['user_stats'] as $userId => $stats) {
                    if (! isset($allUserStats[$userId])) {
                        $allUserStats[$userId] = ['completed' => 0, 'pending' => 0];
                    }
                    $allUserStats[$userId]['completed'] += $stats['completed'];
                    $allUserStats[$userId]['pending'] += $stats['pending'];
                }
            }

            $photosByAlbum = [];
            foreach ($photoResults as $photo) {
                $albumId = $photo['album_id'];
                if (! isset($photosByAlbum[$albumId])) {
                    $photosByAlbum[$albumId] = 0;
                }
                $photosByAlbum[$albumId]++;
            }

            echo "AGGREGATED RESULTS\n";
            echo "======================================================================\n\n";
            echo "Overall Statistics:\n";
            echo "  Total Todos: {$totalItems}\n";
            echo "  Completed: {$totalCompleted} (".round(($totalCompleted / $totalItems) * 100, 2)."%)\n";
            echo "  Pending: {$totalPending} (".round(($totalPending / $totalItems) * 100, 2)."%)\n";
            echo "  Avg Completion Rate: {$avgCompletionRate}%\n";
            echo '  Total Albums: '.count($albums)."\n";
            echo '  Total Photos Processed: '.count($photoResults)."\n";
            echo '  Albums with Photos: '.count($photosByAlbum)."\n";
            echo '  Unique Users: '.count($allUserStats)."\n\n";

            $benchmarks = array_filter(array_map(fn ($p) => $p->getBenchmark(), $chunkPromises));
            if (count($benchmarks) > 0) {
                $totalExecTime = round(array_sum(array_column($benchmarks, 'executionTimeMs')) / 1000, 2);
                $avgExecTime = round(array_sum(array_column($benchmarks, 'executionTimeMs')) / count($benchmarks), 2);

                echo "Performance Metrics:\n";
                echo "======================================================================\n";
                echo '  Chunks Processed: '.count($benchmarks)."\n";
                echo "  Total Processing Time: {$totalExecTime}s\n";
                echo "  Avg Time per Chunk: {$avgExecTime}ms\n";
                echo '  Items per Second: '.round($totalItems / $totalExecTime, 2)."\n\n";
            }

            expect($chunkResults)->toHaveCount(count($chunks))
                ->and($totalItems)->toBe(count($todos))
                ->and($totalCompleted)->toBeGreaterThan(0)
                ->and($totalPending)->toBeGreaterThan(0)
                ->and(count($photoResults))->toBe(count($photos));

            echo "Chunked data processing test passed!\n";
            echo "======================================================================\n\n";

        } finally {
            echo "Cleaning up temporary files...\n";

            $deletedFiles = 0;
            $deletedDirs = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                    $deletedDirs++;
                } else {
                    unlink($file->getPathname());
                    $deletedFiles++;
                }
            }

            rmdir($tempDir);
            $deletedDirs++;

            echo "Cleanup completed!\n";
            echo "  Files deleted: {$deletedFiles}\n";
            echo "  Directories deleted: {$deletedDirs}\n\n";
        }
    });
})->skip(fn () => ! getenv('RUN_REAL_WORLD_TESTS'), 'Real world API tests - set RUN_REAL_WORLD_TESTS=1 to run');
