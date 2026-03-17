<?php

namespace Dimer47\EnvMigrations\Commands;

use Dimer47\EnvMigrations\EnvMigrator;
use Illuminate\Console\Command;

class EnvMigrateCommand extends Command
{
    protected $signature = 'env:migrate {--force : Forcer l\'exécution en production}';

    protected $description = 'Exécuter les migrations d\'environnement en attente';

    public function handle(EnvMigrator $migrator): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Vous êtes en production. Voulez-vous vraiment continuer ?')) {
                $this->info('Migration annulée.');

                return self::SUCCESS;
            }
        }

        $pending = $migrator->getPendingMigrations();

        if (empty($pending)) {
            $this->info('Aucune migration d\'environnement en attente.');

            return self::SUCCESS;
        }

        $this->info('Exécution des migrations d\'environnement...');
        $this->newLine();

        try {
            $results = $migrator->migrate();

            foreach ($results as $result) {
                $this->components->twoColumnDetail(
                    $result['migration'],
                    '<fg=green;options=bold>DONE</>'
                );

                if (! empty($result['description'])) {
                    $this->line("   <fg=gray>{$result['description']}</>");
                }

                if (! empty($result['changes'])) {
                    foreach ($result['changes'] as $change) {
                        $this->displayChange($change);
                    }
                }
            }

            $this->newLine();
            $this->info(count($results).' migration(s) exécutée(s) avec succès.');

            // Rappel de vider le cache de configuration
            $this->newLine();
            $this->warn('N\'oubliez pas d\'exécuter : php artisan config:cache');

        } catch (\Exception $e) {
            $this->error('Erreur lors de la migration : '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function displayChange(array $change): void
    {
        $action = $change['action'] ?? 'unknown';

        switch ($action) {
            case 'set':
            case 'set_if_missing':
                $this->line("   <fg=blue>SET</> {$change['key']}={$change['new_value']}");
                break;
            case 'copy':
                $this->line("   <fg=cyan>COPY</> {$change['source_key']} -> {$change['key']}");
                break;
            case 'rename':
                $this->line("   <fg=yellow>RENAME</> {$change['old_key']} -> {$change['new_key']}");
                break;
            case 'remove':
                $this->line("   <fg=red>REMOVE</> {$change['key']}");
                break;
            case 'append_block':
                $this->line("   <fg=green>APPEND BLOCK</> {$change['identifier']}");
                break;
            case 'ensure_group':
                $keys = implode(', ', $change['variables'] ?? []);
                $this->line("   <fg=magenta>GROUP</> {$keys}");
                break;
            case 'ensure_group_after':
                $keys = implode(', ', $change['variables'] ?? []);
                $this->line("   <fg=magenta>GROUP AFTER</> {$change['after_key']} -> {$keys}");
                break;
        }
    }
}
