<?php

namespace Dimer47\EnvMigrations\Commands;

use Dimer47\EnvMigrations\EnvMigrator;
use Illuminate\Console\Command;

class EnvMigrateStatusCommand extends Command
{
    protected $signature = 'env:migrate:status';

    protected $description = 'Afficher le statut des migrations d\'environnement';

    public function handle(EnvMigrator $migrator): int
    {
        try {
            $status = $migrator->getStatus();

            if (empty($status)) {
                $this->info('Aucune migration d\'environnement trouvée.');
                $this->line('');
                $this->line('Créez votre première migration avec :');
                $this->line('  php artisan env:migrate:make add_my_config');

                return self::SUCCESS;
            }

            $this->newLine();

            $rows = [];
            foreach ($status as $item) {
                $rows[] = [
                    $item['executed'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>',
                    $item['migration'],
                    $item['batch'] ?? '-',
                    $item['executed_at'] ?? '-',
                ];
            }

            $this->table(
                ['Exécutée', 'Migration', 'Batch', 'Date d\'exécution'],
                $rows
            );

            // Résumé
            $executed = collect($status)->where('executed', true)->count();
            $pending = collect($status)->where('executed', false)->count();

            $this->newLine();
            $this->line("<fg=green>{$executed}</> migration(s) exécutée(s), <fg=yellow>{$pending}</> en attente.");

        } catch (\Exception $e) {
            $this->error('Erreur : '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
