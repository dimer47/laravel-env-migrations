<?php

namespace Dimer47\EnvMigrations;

use Illuminate\Support\Facades\File;

abstract class EnvMigration
{
    /**
     * Chemin vers le fichier .env
     */
    protected string $envPath;

    /**
     * Historique des modifications pour le rollback
     */
    protected array $changes = [];

    public function __construct()
    {
        $this->envPath = base_path('.env');
    }

    /**
     * Exécuter la migration
     */
    abstract public function up(): void;

    /**
     * Annuler la migration
     */
    abstract public function down(): void;

    /**
     * Obtenir la description de la migration
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Obtenir l'historique des modifications
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Définir une variable d'environnement
     */
    protected function set(string $key, string $value, ?string $comment = null): self
    {
        $oldValue = $this->get($key);
        $this->changes[] = [
            'action' => 'set',
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $value,
        ];

        $this->setEnvValue($key, $value);

        return $this;
    }

    /**
     * Obtenir la valeur d'une variable d'environnement depuis le fichier .env
     */
    protected function get(string $key): ?string
    {
        $content = File::get($this->envPath);

        if (preg_match("/^{$key}=(.*)$/m", $content, $matches)) {
            return trim($matches[1], '"\'');
        }

        return null;
    }

    /**
     * Copier la valeur d'une variable vers une autre
     */
    protected function copy(string $sourceKey, string $targetKey): self
    {
        $value = $this->get($sourceKey);

        if ($value !== null) {
            $this->set($targetKey, $value);
            $this->changes[array_key_last($this->changes)]['action'] = 'copy';
            $this->changes[array_key_last($this->changes)]['source_key'] = $sourceKey;
        }

        return $this;
    }

    /**
     * Supprimer une variable d'environnement
     */
    protected function remove(string $key): self
    {
        $oldValue = $this->get($key);

        if ($oldValue !== null) {
            $this->changes[] = [
                'action' => 'remove',
                'key' => $key,
                'old_value' => $oldValue,
            ];

            $this->removeEnvValue($key);
        }

        return $this;
    }

    /**
     * Renommer une variable d'environnement (en conservant sa position)
     */
    protected function rename(string $oldKey, string $newKey): self
    {
        $content = File::get($this->envPath);
        $value = $this->get($oldKey);

        if ($value !== null) {
            $this->changes[] = [
                'action' => 'rename',
                'old_key' => $oldKey,
                'new_key' => $newKey,
                'value' => $value,
            ];

            // Remplacer directement la ligne pour conserver la position
            $escapedValue = $this->escapeEnvValue($value);
            $content = preg_replace(
                "/^{$oldKey}=.*/m",
                "{$newKey}={$escapedValue}",
                $content
            );

            File::put($this->envPath, $content);
        }

        return $this;
    }

    /**
     * Ajouter un bloc de configuration à la fin du fichier
     */
    protected function appendBlock(string $block, string $identifier): self
    {
        $content = File::get($this->envPath);

        // Vérifier si le bloc existe déjà (via l'identifiant)
        if (str_contains($content, $identifier)) {
            return $this;
        }

        $this->changes[] = [
            'action' => 'append_block',
            'identifier' => $identifier,
            'block' => $block,
        ];

        // Ajouter une ligne vide si nécessaire
        if (! str_ends_with(trim($content), '')) {
            $content .= "\n";
        }

        $content .= "\n".$block;
        File::put($this->envPath, $content);

        return $this;
    }

    /**
     * Supprimer un bloc de configuration
     */
    protected function removeBlock(string $startMarker, string $endMarker): self
    {
        $content = File::get($this->envPath);

        $pattern = "/\n?".preg_quote($startMarker, '/').'.*?'.preg_quote($endMarker, '/')."[^\n]*\n?/s";

        if (preg_match($pattern, $content, $matches)) {
            $this->changes[] = [
                'action' => 'remove_block',
                'start_marker' => $startMarker,
                'end_marker' => $endMarker,
                'content' => $matches[0],
            ];

            $content = preg_replace($pattern, "\n", $content);
            File::put($this->envPath, $content);
        }

        return $this;
    }

    /**
     * Vérifier si une variable existe
     */
    protected function exists(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Définir une valeur uniquement si elle n'existe pas
     */
    protected function setIfMissing(string $key, string $value): self
    {
        if (! $this->exists($key)) {
            $this->set($key, $value);
            $this->changes[array_key_last($this->changes)]['action'] = 'set_if_missing';
        }

        return $this;
    }

    /**
     * S'assurer qu'un groupe de variables existe dans un ordre spécifique
     * Les variables sont réorganisées ensemble à la position de la première variable trouvée,
     * avec des valeurs par défaut si absentes
     *
     * @param  array  $variables  ['KEY' => 'default_value', ...] dans l'ordre souhaité
     * @param  string|null  $comment  Commentaire à ajouter avant le groupe
     */
    protected function ensureGroup(array $variables, ?string $comment = null): self
    {
        $content = File::get($this->envPath);

        // Sauvegarder les valeurs existantes et trouver la position de la première variable
        $existingValues = [];
        $firstPosition = null;

        foreach (array_keys($variables) as $key) {
            $existingValues[$key] = $this->get($key);

            // Trouver la position de cette variable dans le fichier
            if (preg_match("/^{$key}=.*/m", $content, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];
                if ($firstPosition === null || $position < $firstPosition) {
                    $firstPosition = $position;
                }
            }
        }

        // Supprimer toutes les anciennes occurrences
        foreach (array_keys($variables) as $key) {
            $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
        }

        // Construire le nouveau bloc
        $block = '';
        if ($comment) {
            $block .= "{$comment}\n";
        }

        foreach ($variables as $key => $defaultValue) {
            // Utiliser la valeur existante si disponible, sinon la valeur par défaut
            $value = $existingValues[$key] ?? $defaultValue;
            $escapedValue = $this->escapeEnvValue($value);
            $block .= "{$key}={$escapedValue}\n";
        }

        // Insérer le bloc à la position de la première variable trouvée, ou à la fin
        if ($firstPosition !== null) {
            // Ajuster la position car on a supprimé des lignes avant
            // On recalcule en comptant les caractères supprimés avant cette position
            $contentBefore = File::get($this->envPath);
            $removedBefore = 0;

            foreach (array_keys($variables) as $key) {
                if (preg_match("/^{$key}=.*\n?/m", $contentBefore, $matches, PREG_OFFSET_CAPTURE)) {
                    if ($matches[0][1] < $firstPosition) {
                        $removedBefore += strlen($matches[0][0]);
                    }
                }
            }

            // Recalculer la position dans le contenu modifié
            $adjustedPosition = $firstPosition - $removedBefore;

            // S'assurer qu'on est au début d'une ligne
            if ($adjustedPosition > 0 && $content[$adjustedPosition - 1] !== "\n") {
                // Trouver le début de la ligne
                $adjustedPosition = strrpos(substr($content, 0, $adjustedPosition), "\n");
                if ($adjustedPosition === false) {
                    $adjustedPosition = 0;
                } else {
                    $adjustedPosition++; // Après le \n
                }
            }

            $content = substr($content, 0, $adjustedPosition).$block.substr($content, $adjustedPosition);
        } else {
            // Aucune variable n'existait, ajouter à la fin
            $content = rtrim($content)."\n\n".$block;
        }

        // Nettoyer les lignes vides multiples
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($this->envPath, $content);

        $this->changes[] = [
            'action' => 'ensure_group',
            'variables' => array_keys($variables),
            'comment' => $comment,
        ];

        return $this;
    }

    /**
     * S'assurer qu'un groupe de variables existe, inséré après une variable repère.
     * Si les variables existent déjà, elles sont réorganisées en conservant leurs valeurs.
     * Sinon, elles sont insérées juste après $afterKey.
     *
     * @param  string  $afterKey  La variable repère après laquelle insérer le groupe
     * @param  array  $variables  ['KEY' => 'default_value', ...] dans l'ordre souhaité
     * @param  string|null  $comment  Commentaire à ajouter avant le groupe
     */
    protected function ensureGroupAfter(string $afterKey, array $variables, ?string $comment = null): self
    {
        $content = File::get($this->envPath);

        // Sauvegarder les valeurs existantes
        $existingValues = [];
        $anyExists = false;

        foreach (array_keys($variables) as $key) {
            $existingValues[$key] = $this->get($key);
            if ($existingValues[$key] !== null) {
                $anyExists = true;
            }
        }

        // Si au moins une variable existe déjà, utiliser ensureGroup classique
        if ($anyExists) {
            return $this->ensureGroup($variables, $comment);
        }

        // Supprimer toutes les anciennes occurrences (au cas où)
        foreach (array_keys($variables) as $key) {
            $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
        }

        // Construire le nouveau bloc
        $block = '';
        if ($comment) {
            $block .= "{$comment}\n";
        }

        foreach ($variables as $key => $defaultValue) {
            $value = $existingValues[$key] ?? $defaultValue;
            $escapedValue = $this->escapeEnvValue($value);
            $block .= "{$key}={$escapedValue}\n";
        }

        // Trouver la position de la variable repère
        if (preg_match("/^{$afterKey}=.*\n/m", $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPosition = $matches[0][1] + strlen($matches[0][0]);
            $content = substr($content, 0, $insertPosition)."\n".$block.substr($content, $insertPosition);
        } else {
            // Variable repère introuvable, ajouter à la fin
            $content = rtrim($content)."\n\n".$block;
        }

        // Nettoyer les lignes vides multiples
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($this->envPath, $content);

        $this->changes[] = [
            'action' => 'ensure_group_after',
            'after_key' => $afterKey,
            'variables' => array_keys($variables),
            'comment' => $comment,
        ];

        return $this;
    }

    /**
     * Écrire une valeur dans le fichier .env
     */
    private function setEnvValue(string $key, string $value): void
    {
        $content = File::get($this->envPath);

        // Échapper les valeurs contenant des espaces ou caractères spéciaux
        $escapedValue = $this->escapeEnvValue($value);

        // Si la clé existe, la mettre à jour
        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$escapedValue}",
                $content
            );
        } else {
            // Sinon, l'ajouter à la fin
            $content = rtrim($content)."\n{$key}={$escapedValue}\n";
        }

        File::put($this->envPath, $content);
    }

    /**
     * Supprimer une valeur du fichier .env
     */
    private function removeEnvValue(string $key): void
    {
        $content = File::get($this->envPath);
        $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
        File::put($this->envPath, $content);
    }

    /**
     * Échapper une valeur pour le fichier .env
     */
    private function escapeEnvValue(string $value): string
    {
        // Si la valeur contient des espaces, des guillemets ou des caractères spéciaux
        if (preg_match('/[\s"\'#]/', $value) || $value === '') {
            // Échapper les guillemets doubles et entourer de guillemets
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
