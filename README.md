# ⚙️ Laravel Env Migrations

![GitHub release (latest by date)](https://img.shields.io/github/v/release/dimer47/laravel-env-migrations?style=flat-square&logo=github) ![GitHub License](https://img.shields.io/github/license/dimer47/laravel-env-migrations?style=flat-square) ![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![GitHub last commit](https://img.shields.io/github/last-commit/dimer47/laravel-env-migrations?style=flat-square) ![GitHub repo size](https://img.shields.io/github/repo-size/dimer47/laravel-env-migrations?style=flat-square)

> Versioned migration system for Laravel's `.env` file, similar to database migrations. 🗂️

🇫🇷 [Lire en français](README.fr.md)

## 🎉 Features

- 🔄 **Versioned migrations** — manage `.env` changes just like database migrations
- 📦 **Database tracking** — tracking table with batches and JSON history
- ⏪ **Rollback** — revert the last batch of changes
- 📊 **Status** — view the state of all migrations
- 🧩 **Laravel ready** — auto-discovery, publishable config, Artisan commands
- 🛡️ **Production safe** — mandatory confirmation in production (unless `--force`)
- 🔧 **11 methods** — set, get, remove, rename, copy, group, block, etc.

## 📍 Installation

### Via Composer (packagist)

```bash
composer require dimer47/laravel-env-migrations
```

### 🧪 Via a local repository (development)

Add to the host project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravel-env-migrations"
        }
    ],
    "require": {
        "dimer47/laravel-env-migrations": "*"
    }
}
```

Then:

```bash
composer update dimer47/laravel-env-migrations
```

## 🔧 Configuration

Publish the package files:

```bash
# Publish the config
php artisan vendor:publish --tag=env-migrations-config

# Publish the tracking table migration
php artisan vendor:publish --tag=env-migrations-migrations

# Run the migration
php artisan migrate
```

### ⚙️ Configuration options (`config/env-migrations.php`)

| Option | Description | Default |
|--------|-------------|---------|
| `table` | Name of the tracking table | `env_migrations` |
| `path` | Directory for migration files | `database/env-migrations` |

## 🚀 Quick Start

### ➕ Create a migration

```bash
php artisan env:migrate:make add_redis_configuration
```

This creates a file in `database/env-migrations/` with a ready-to-use template.

### ✏️ Write a migration

```php
<?php

use Dimer47\EnvMigrations\EnvMigration;

return new class extends EnvMigration
{
    public function getDescription(): string
    {
        return 'Configure Redis for cache and queues';
    }

    public function up(): void
    {
        // Set a variable
        $this->set('CACHE_DRIVER', 'redis');

        // Set only if missing
        $this->setIfMissing('REDIS_HOST', '127.0.0.1');

        // Rename a variable (keeps its position)
        $this->rename('OLD_REDIS_PORT', 'REDIS_PORT');

        // Copy a variable
        $this->copy('REDIS_HOST', 'REDIS_CACHE_HOST');

        // Remove a variable
        $this->remove('DEPRECATED_CACHE_KEY');

        // Group variables together
        $this->ensureGroup([
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
        ], '# Redis Configuration');

        // Insert a group after a reference variable
        $this->ensureGroupAfter('CACHE_DRIVER', [
            'CACHE_PREFIX' => 'myapp',
            'CACHE_TTL' => '3600',
        ]);

        // Append a multi-line block
        $this->appendBlock(<<<'ENV'
# Queue Configuration
QUEUE_CONNECTION=redis
QUEUE_RETRY_AFTER=90
ENV, 'QUEUE_CONNECTION');
    }

    public function down(): void
    {
        $this->set('CACHE_DRIVER', 'file');
        $this->remove('REDIS_CACHE_HOST');
        $this->rename('REDIS_PORT', 'OLD_REDIS_PORT');
        $this->removeBlock('# Queue Configuration', 'QUEUE_RETRY_AFTER');
    }
};
```

### ▶️ Run migrations

```bash
php artisan env:migrate
php artisan env:migrate --force  # Skip confirmation in production
```

### 📊 View status

```bash
php artisan env:migrate:status
```

### ⏪ Rollback last batch

```bash
php artisan env:migrate:rollback
php artisan env:migrate:rollback --force  # Skip confirmation in production
```

## 📚 Available methods

| Method | Description |
|--------|-------------|
| `set($key, $value)` | Set or update a variable |
| `get($key)` | Read a variable's value |
| `remove($key)` | Remove a variable |
| `rename($oldKey, $newKey)` | Rename a variable (keeps its position) |
| `copy($source, $target)` | Copy a variable to a new key |
| `exists($key)` | Check if a variable exists |
| `setIfMissing($key, $value)` | Set only if not already defined |
| `ensureGroup($vars, $comment)` | Group variables together |
| `ensureGroupAfter($afterKey, $vars, $comment)` | Insert a group after a reference variable |
| `appendBlock($block, $identifier)` | Append a multi-line block (duplicate-safe) |
| `removeBlock($start, $end)` | Remove a block by markers |

## 🖥️ Artisan Commands

| Command | Description |
|---------|-------------|
| `env:migrate` | ▶️ Run pending migrations |
| `env:migrate:rollback` | ⏪ Rollback the last batch |
| `env:migrate:status` | 📊 Display migrations status |
| `env:migrate:make {name}` | ➕ Create a new migration |

## 🗄️ Tracking table schema

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT` | Auto-incremented primary key |
| `migration` | `VARCHAR(255)` | Unique migration name |
| `batch` | `INT` | Execution batch number |
| `changes` | `JSON` | Detailed change history |
| `executed_at` | `TIMESTAMP` | Execution date |

## 📄 License

MIT — See [LICENSE](LICENSE)
