<?php

namespace Dimer47\EnvMigrations\Commands;

use Dimer47\EnvMigrations\EnvMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EnvMigrateMakeCommand extends Command
{
    protected $signature = 'env:migrate:make {name : Le nom de la migration (en snake_case)}';

    protected $description = 'Créer une nouvelle migration d\'environnement';

    public function handle(EnvMigrator $migrator): int
    {
        $name = $this->argument('name');

        // Convertir en snake_case si nécessaire
        $name = Str::snake($name);

        try {
            $path = $migrator->create($name);

            $this->components->info('Migration d\'environnement créée avec succès.');
            $this->line('');
            $this->line("  <fg=green>CREATE</> {$path}");
            $this->newLine();
            $this->line('Modifiez ce fichier pour définir les changements à apporter au fichier .env');
            $this->line('Puis exécutez : <fg=cyan>php artisan env:migrate</>');

        } catch (\Exception $e) {
            $this->error('Erreur lors de la création : '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
