<?php

namespace Dimer47\EnvMigrations\Commands;

use Dimer47\EnvMigrations\EnvMigrator;
use Illuminate\Console\Command;

class EnvMigrateRollbackCommand extends Command
{
    protected $signature = 'env:migrate:rollback {--force : Forcer l\'exécution en production}';

    protected $description = 'Annuler le dernier batch de migrations d\'environnement';

    public function handle(EnvMigrator $migrator): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Vous êtes en production. Voulez-vous vraiment annuler les migrations ?')) {
                $this->info('Rollback annulé.');

                return self::SUCCESS;
            }
        }

        $this->info('Annulation des migrations d\'environnement...');
        $this->newLine();

        try {
            $results = $migrator->rollback();

            if (empty($results)) {
                $this->info('Aucune migration à annuler.');

                return self::SUCCESS;
            }

            foreach ($results as $result) {
                $this->components->twoColumnDetail(
                    $result['migration'],
                    '<fg=yellow;options=bold>ROLLED BACK</>'
                );
            }

            $this->newLine();
            $this->info(count($results).' migration(s) annulée(s).');

            // Rappel de vider le cache de configuration
            $this->newLine();
            $this->warn('N\'oubliez pas d\'exécuter : php artisan config:cache');

        } catch (\Exception $e) {
            $this->error('Erreur lors du rollback : '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
