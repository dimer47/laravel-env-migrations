<?php

namespace Dimer47\EnvMigrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnvMigrator
{
    protected string $migrationsPath;

    protected string $table;

    public function __construct()
    {
        $this->migrationsPath = config('env-migrations.path', database_path('env-migrations'));
        $this->table = config('env-migrations.table', 'env_migrations');
    }

    /**
     * Exécuter toutes les migrations en attente
     */
    public function migrate(): array
    {
        $this->ensureTableExists();

        $pending = $this->getPendingMigrations();
        $batch = $this->getNextBatchNumber();
        $results = [];

        foreach ($pending as $migration) {
            $instance = $this->resolveMigration($migration);

            if ($instance) {
                $instance->up();

                DB::table($this->table)->insert([
                    'migration' => $migration,
                    'batch' => $batch,
                    'changes' => json_encode($instance->getChanges()),
                    'executed_at' => now(),
                ]);

                $results[] = [
                    'migration' => $migration,
                    'status' => 'success',
                    'description' => $instance->getDescription(),
                    'changes' => $instance->getChanges(),
                ];
            }
        }

        return $results;
    }

    /**
     * Annuler le dernier batch de migrations
     */
    public function rollback(): array
    {
        $this->ensureTableExists();

        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            return [];
        }

        $migrations = DB::table($this->table)
            ->where('batch', $lastBatch)
            ->orderByDesc('id')
            ->get();

        $results = [];

        foreach ($migrations as $record) {
            $instance = $this->resolveMigration($record->migration);

            if ($instance) {
                $instance->down();

                DB::table($this->table)
                    ->where('id', $record->id)
                    ->delete();

                $results[] = [
                    'migration' => $record->migration,
                    'status' => 'rolled_back',
                ];
            }
        }

        return $results;
    }

    /**
     * Obtenir le statut de toutes les migrations
     */
    public function getStatus(): array
    {
        $this->ensureTableExists();

        $allMigrations = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations();

        $status = [];

        foreach ($allMigrations as $migration) {
            $record = $executed->firstWhere('migration', $migration);

            $status[] = [
                'migration' => $migration,
                'executed' => $record !== null,
                'batch' => $record?->batch,
                'executed_at' => $record?->executed_at,
            ];
        }

        return $status;
    }

    /**
     * Créer une nouvelle migration
     */
    public function create(string $name): string
    {
        $this->ensureMigrationsDirectoryExists();

        $timestamp = date('Y_m_d_His');
        $className = Str::studly($name);
        $filename = "{$timestamp}_{$name}.php";
        $path = $this->migrationsPath.'/'.$filename;

        $stub = $this->getMigrationStub();
        File::put($path, $stub);

        return $path;
    }

    /**
     * Obtenir les migrations en attente
     */
    public function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations()->pluck('migration')->toArray();

        return array_diff($allMigrations, $executed);
    }

    /**
     * Obtenir tous les fichiers de migration
     */
    protected function getAllMigrationFiles(): array
    {
        if (! File::isDirectory($this->migrationsPath)) {
            return [];
        }

        $files = File::files($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $migrations[] = $file->getFilenameWithoutExtension();
            }
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Obtenir les migrations déjà exécutées
     */
    protected function getExecutedMigrations(): Collection
    {
        if (! Schema::hasTable($this->table)) {
            return collect();
        }

        return DB::table($this->table)->get();
    }

    /**
     * Résoudre et instancier une migration
     */
    protected function resolveMigration(string $migration): ?EnvMigration
    {
        $path = $this->migrationsPath.'/'.$migration.'.php';

        if (! File::exists($path)) {
            return null;
        }

        $class = require $path;

        if ($class instanceof EnvMigration) {
            return $class;
        }

        return null;
    }

    /**
     * Obtenir le prochain numéro de batch
     */
    protected function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Obtenir le dernier numéro de batch
     */
    protected function getLastBatchNumber(): int
    {
        if (! Schema::hasTable($this->table)) {
            return 0;
        }

        return (int) DB::table($this->table)->max('batch') ?? 0;
    }

    /**
     * S'assurer que la table existe
     */
    protected function ensureTableExists(): void
    {
        if (! Schema::hasTable($this->table)) {
            throw new \RuntimeException(
                "La table '{$this->table}' n'existe pas. Exécutez d'abord 'php artisan migrate'."
            );
        }
    }

    /**
     * S'assurer que le dossier des migrations existe
     */
    protected function ensureMigrationsDirectoryExists(): void
    {
        if (! File::isDirectory($this->migrationsPath)) {
            File::makeDirectory($this->migrationsPath, 0755, true);
        }
    }

    /**
     * Obtenir le template de migration
     */
    protected function getMigrationStub(): string
    {
        $stubPath = __DIR__.'/Stubs/env-migration.stub';

        if (File::exists($stubPath)) {
            return File::get($stubPath);
        }

        return $this->getDefaultStub();
    }

    /**
     * Template par défaut si le stub est introuvable
     */
    protected function getDefaultStub(): string
    {
        return <<<'PHP'
<?php

use Dimer47\EnvMigrations\EnvMigration;

return new class extends EnvMigration
{
    /**
     * Description de la migration
     */
    public function getDescription(): string
    {
        return 'Description de la migration';
    }

    /**
     * Exécuter la migration
     */
    public function up(): void
    {
        //
    }

    /**
     * Annuler la migration
     */
    public function down(): void
    {
        //
    }
};
PHP;
    }
}
