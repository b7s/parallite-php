# Configuration

Customize Parallite by adding a `parallite.json` file at your project root. The file controls PHP preload files,
benchmark mode, and Go daemon overrides.

## Example

```json
{
  "php_includes": [
    "bootstrap/app.php",
    "config/database.php",
    "/var/www/shared/helpers.php"
  ],
  "enable_benchmark": true,
  "go_overrides": {
    "timeout_ms": 60000,
    "fixed_workers": 4,
    "prefix_name": "myapp_worker",
    "fail_mode": "continue"
  }
}
```

## PHP Settings

### `php_includes`

List of PHP files loaded inside every worker before processing tasks. Paths may be relative (resolved from the project
root) or absolute.

Typical use cases:

- Bootstrap frameworks (Laravel, Symfony)
- Register custom autoloader
- Load configuration helpers

Default: `[]`

### `enable_benchmark`

Enable global benchmark collection for all tasks.

- `true` · always capture metrics
- `false` · disabled unless enabled per task (default)

## Go Overrides (`go_overrides`)

The Go daemon orchestrates workers. Override specific keys when needed.

### `timeout_ms`

Maximum task duration in milliseconds. Default: `600000` (10 minutes).

### `fixed_workers`

Number of worker processes to keep alive.

- `0` · auto-scale with CPU cores (default)
- `> 0` · fixed worker pool size

### `prefix_name`

Worker process name prefix. Helps identify Parallite workers in system tools. Default: `"parallite_worker"`.

### `fail_mode`

Controls daemon reaction to failures.

- `"continue"` · continue processing (default)
- `"stop"` · terminate daemon on the first failure

## Laravel Bootstrap

Create [`bootstrap/parallite.php`](troubleshooting.md#laravel-integration-issues) to load the application without
triggering the HTTP kernel.

> See Laravel Bootstrap [code here](../examples/boot-laravel-app.php).

Then include it via `php_includes`:

```json
{
  "php_includes": [
    "bootstrap/parallite.php"
  ]
}
```

Avoid the HTTP kernel because request lifecycle termination will shut down workers prematurely.
