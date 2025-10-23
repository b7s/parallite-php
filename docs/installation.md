# Installation

Parallite ships as a Composer package plus a lightweight Go daemon. Install the package, then install the binary.

## Requirements

- PHP 8.3+
- ext-sockets
- ext-pcntl
- ext-zip
- rybakit/msgpack
- opis/closure
- Composer 2+

## Package

```bash
composer require parallite/parallite-php
```

## Parallite Binary

After composer install, install the daemon orchestrator binary. Two options are available.

### Option 1 · Automatic (Recommended)

Add the install/update scripts to your `composer.json`:

```json
{
  "scripts": {
    "post-install-cmd": [
      "@php vendor/parallite/parallite-php/bin/parallite-install"
    ],
    "post-update-cmd": [
      "@php vendor/parallite/parallite-php/bin/parallite-update"
    ]
  }
}
```

> See `composer.json.example` for a full example.

Then run `composer install` or `composer update`. The binary is placed at
`vendor/parallite/parallite-php/bin/parallite-bin/parallite-{version}` (or `.exe` on Windows).

### Option 2 · One-Time Install

Run the installer manually:

```bash
php vendor/parallite/parallite-php/bin/parallite-install
```

#### Flags

- `--force` · reinstall even if the binary already exists
- `--version=X.Y.Z` · install a specific release (`1.2.3` or `v1.2.3`)

Examples:

```bash
php vendor/parallite/parallite-php/bin/parallite-install --force
php vendor/parallite/parallite-php/bin/parallite-install --version=1.2.3
```

### Updating the Binary

Use the update script:

```bash
php vendor/parallite/parallite-php/bin/parallite-update
```

#### Update Flags

- `--version=X.Y.Z` · install a specific release directly

Examples:

```bash
php vendor/parallite/parallite-php/bin/parallite-update --force
php vendor/parallite/parallite-php/bin/parallite-update --version=1.2.3
```

## Requirements Explained

- **PHP 8.3+**: The minimum PHP version required for all features to work correctly.
- **ext-sockets**: Required for inter-process communication between PHP and the Go daemon.
- **ext-pcntl**: Used for process control functions to manage child processes.
- **ext-zip**: Required for handling zip archives during the installation and update process for Parallite Go binary,
  from GitHub.
- **rybakit/msgpack**: A pure PHP implementation of MessagePack for efficient data serialization.
- **opis/closure**: Provides tools to serialize closures and anonymous functions.
- **Composer 2+**: Required for dependency management and package installation.
