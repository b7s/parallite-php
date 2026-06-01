# Configuration

Customize Parallite by adding a `parallite.json` file at your project root.

## Example

```json
{
    "php_includes": [
        "bootstrap/app.php",
        "config/database.php"
    ],
    "enable_benchmark": true
}
```

## Settings

### `php_includes`

List of PHP files loaded inside every forked child process before executing tasks. Paths may be relative (resolved from the project root) or absolute.

Typical use cases:

- Bootstrap frameworks (Laravel, Symfony)
- Register custom autoloaders
- Load configuration helpers

Default: `[]`

### `enable_benchmark`

Enable global benchmark collection for all tasks.

- `true` — always capture metrics
- `false` — disabled unless enabled per task (default)

## Laravel Bootstrap

Create `bootstrap/parallite.php` to load the application without triggering the HTTP kernel:

```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
```

Then include it via `php_includes`:

```json
{
    "php_includes": [
        "bootstrap/parallite.php"
    ]
}
```

Avoid the HTTP kernel because request lifecycle termination will shut down the forked process prematurely.
