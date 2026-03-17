# ⚙️ Laravel Env Migrations

![GitHub release (latest by date)](https://img.shields.io/github/v/release/dimer47/laravel-env-migrations?style=flat-square&logo=github) ![GitHub License](https://img.shields.io/github/license/dimer47/laravel-env-migrations?style=flat-square) ![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![GitHub last commit](https://img.shields.io/github/last-commit/dimer47/laravel-env-migrations?style=flat-square) ![GitHub repo size](https://img.shields.io/github/repo-size/dimer47/laravel-env-migrations?style=flat-square)

> Système de migrations versionnées pour le fichier `.env` de Laravel, similaire aux migrations de base de données. 🗂️

🇬🇧 [Read in English](README.md)

## 🎉 Features

- 🔄 **Migrations versionnées** — gérez les changements `.env` comme les migrations DB
- 📦 **Suivi en base de données** — table de tracking avec batches et historique JSON
- ⏪ **Rollback** — annulez le dernier batch de modifications
- 📊 **Statut** — visualisez l'état de toutes les migrations
- 🧩 **Laravel ready** — auto-discovery, config publiable, commandes Artisan
- 🛡️ **Production safe** — confirmation obligatoire en production (sauf `--force`)
- 🔧 **11 méthodes** — set, get, remove, rename, copy, group, block, etc.

## 📍 Installation

### Via Composer (packagist)

```bash
composer require dimer47/laravel-env-migrations
```

### 🧪 Via un repository local (développement)

Ajoutez dans le `composer.json` du projet hôte :

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

Puis :

```bash
composer update dimer47/laravel-env-migrations
```

## 🔧 Configuration

Publiez les fichiers du package :

```bash
# Publier la config
php artisan vendor:publish --tag=env-migrations-config

# Publier la migration de la table de suivi
php artisan vendor:publish --tag=env-migrations-migrations

# Exécuter la migration
php artisan migrate
```

### ⚙️ Options de configuration (`config/env-migrations.php`)

| Option | Description | Défaut |
|--------|-------------|--------|
| `table` | Nom de la table de suivi | `env_migrations` |
| `path` | Répertoire des fichiers de migration | `database/env-migrations` |

## 🚀 Quick Start

### ➕ Créer une migration

```bash
php artisan env:migrate:make add_redis_configuration
```

Cela crée un fichier dans `database/env-migrations/` avec un template prêt à l'emploi.

### ✏️ Écrire une migration

```php
<?php

use Dimer47\EnvMigrations\EnvMigration;

return new class extends EnvMigration
{
    public function getDescription(): string
    {
        return 'Configurer Redis pour le cache et les queues';
    }

    public function up(): void
    {
        // Définir une variable
        $this->set('CACHE_DRIVER', 'redis');

        // Définir seulement si absente
        $this->setIfMissing('REDIS_HOST', '127.0.0.1');

        // Renommer une variable (conserve sa position)
        $this->rename('OLD_REDIS_PORT', 'REDIS_PORT');

        // Copier une variable
        $this->copy('REDIS_HOST', 'REDIS_CACHE_HOST');

        // Supprimer une variable
        $this->remove('DEPRECATED_CACHE_KEY');

        // Grouper des variables ensemble
        $this->ensureGroup([
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
        ], '# Configuration Redis');

        // Insérer un groupe après une variable repère
        $this->ensureGroupAfter('CACHE_DRIVER', [
            'CACHE_PREFIX' => 'myapp',
            'CACHE_TTL' => '3600',
        ]);

        // Ajouter un bloc multi-ligne
        $this->appendBlock(<<<'ENV'
# Configuration Queue
QUEUE_CONNECTION=redis
QUEUE_RETRY_AFTER=90
ENV, 'QUEUE_CONNECTION');
    }

    public function down(): void
    {
        $this->set('CACHE_DRIVER', 'file');
        $this->remove('REDIS_CACHE_HOST');
        $this->rename('REDIS_PORT', 'OLD_REDIS_PORT');
        $this->removeBlock('# Configuration Queue', 'QUEUE_RETRY_AFTER');
    }
};
```

### ▶️ Exécuter les migrations

```bash
php artisan env:migrate
php artisan env:migrate --force  # Sans confirmation en production
```

### 📊 Voir le statut

```bash
php artisan env:migrate:status
```

### ⏪ Annuler le dernier batch

```bash
php artisan env:migrate:rollback
php artisan env:migrate:rollback --force  # Sans confirmation en production
```

## 📚 Méthodes disponibles

| Méthode | Description |
|---------|-------------|
| `set($key, $value)` | Définir/mettre à jour une variable |
| `get($key)` | Lire la valeur d'une variable |
| `remove($key)` | Supprimer une variable |
| `rename($oldKey, $newKey)` | Renommer une variable (conserve sa position) |
| `copy($source, $target)` | Copier une variable vers une nouvelle clé |
| `exists($key)` | Vérifier si une variable existe |
| `setIfMissing($key, $value)` | Définir seulement si absente |
| `ensureGroup($vars, $comment)` | Grouper des variables ensemble |
| `ensureGroupAfter($afterKey, $vars, $comment)` | Insérer un groupe après une variable repère |
| `appendBlock($block, $identifier)` | Ajouter un bloc multi-ligne (anti-doublon) |
| `removeBlock($start, $end)` | Supprimer un bloc par marqueurs |

## 🖥️ Commandes Artisan

| Commande | Description |
|----------|-------------|
| `env:migrate` | ▶️ Exécuter les migrations en attente |
| `env:migrate:rollback` | ⏪ Annuler le dernier batch |
| `env:migrate:status` | 📊 Afficher le statut des migrations |
| `env:migrate:make {name}` | ➕ Créer une nouvelle migration |

## 🗄️ Schéma de la table de suivi

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | `BIGINT` | Clé primaire auto-incrémentée |
| `migration` | `VARCHAR(255)` | Nom unique de la migration |
| `batch` | `INT` | Numéro du batch d'exécution |
| `changes` | `JSON` | Historique détaillé des modifications |
| `executed_at` | `TIMESTAMP` | Date d'exécution |

## 📄 License

MIT — Voir [LICENSE](LICENSE)
