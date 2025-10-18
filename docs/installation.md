# Installation

Parallite ships as a Composer package plus a lightweight Go daemon. Install the package, then install the binary.

## Requirements

- PHP 8.3 or higher
- `ext-msgpack`
- `ext-sockets`
- `ext-zip`
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

Then run `composer install` or `composer update`. The binary is placed at `vendor/parallite/parallite-php/bin/parallite-bin/parallite-{version}.bin` (or `.exe` on Windows).

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

- `--force` · allow updates across major versions
- `--version=X.Y.Z` · install a specific release directly

Examples:

```bash
php vendor/parallite/parallite-php/bin/parallite-update --force
php vendor/parallite/parallite-php/bin/parallite-update --version=1.2.3
```
