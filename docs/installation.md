# Installation

Parallite is a pure PHP library — no external binary, no daemon, no post-install scripts.

## Requirements

- PHP 8.3+
- ext-pcntl (built-in on Linux/macOS — enables fork mode)
- ext-posix (built-in on Linux/macOS — enables fork mode)

## Install

```bash
composer require parallite/parallite-php
```

That's it. No binary to download, no daemon to start.

## Verify

```bash
php -m | grep -E 'pcntl|posix'
```

Both extensions should appear. If they don't, install them:

```bash
# Ubuntu/Debian
sudo apt-get install php-pcntl php-posix

# macOS (Homebrew PHP — usually included by default)
# No action needed
```

## Platform Notes

| Platform | Mode | Details |
| --- | --- | --- |
| Linux | Fork (parallel) | `ext-pcntl` and `ext-posix` are built-in |
| macOS | Fork (parallel) | `ext-pcntl` and `ext-posix` are built-in |
| Windows | Sequential | No `pcntl_fork` — closures run one after another |

On Windows, Parallite automatically falls back to sequential execution. Your code works the same way, just without parallelism.
