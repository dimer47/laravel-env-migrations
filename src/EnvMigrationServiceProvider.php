<?php

namespace Dimer47\EnvMigrations;

use Dimer47\EnvMigrations\Commands\EnvMigrateCommand;
use Dimer47\EnvMigrations\Commands\EnvMigrateMakeCommand;
use Dimer47\EnvMigrations\Commands\EnvMigrateRollbackCommand;
use Dimer47\EnvMigrations\Commands\EnvMigrateStatusCommand;
use Illuminate\Support\ServiceProvider;

class EnvMigrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/env-migrations.php', 'env-migrations');

        $this->app->singleton(EnvMigrator::class, function ($app) {
            return new EnvMigrator;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publier la config
            $this->publishes([
                __DIR__.'/../config/env-migrations.php' => config_path('env-migrations.php'),
            ], 'env-migrations-config');

            // Publier la migration de la table de suivi
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'env-migrations-migrations');

            // Enregistrer les commandes
            $this->commands([
                EnvMigrateCommand::class,
                EnvMigrateRollbackCommand::class,
                EnvMigrateStatusCommand::class,
                EnvMigrateMakeCommand::class,
            ]);
        }
    }
}
