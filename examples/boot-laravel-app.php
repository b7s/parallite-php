<?php

declare(strict_types=1);

/**
 * Parallite Bootstrap File
 *
 * This file bootstraps the Laravel application for Parallite workers
 * without executing HTTP requests or sending responses.
 */

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));

// Register The Auto Loader
require __DIR__.'/../vendor/autoload.php';

// Bootstrap The Application
$app = require_once __DIR__.'/app.php';

// Bootstrap the console kernel (not HTTP kernel)
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
