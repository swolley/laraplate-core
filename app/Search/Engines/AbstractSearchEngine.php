<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Exception;
use Laravel\Scout\Engines\Engine;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Traits\Searchable;
use Laravel\Scout\Searchable as ScoutSearchable;
use Modules\Core\Search\Contracts\SearchEngineInterface;

/**
 * Classe base astratta per i motori di ricerca.
 */
abstract class AbstractSearchEngine extends Engine implements SearchEngineInterface
{
    /**
     * Configurazione del motore di ricerca.
     */
    protected array $config;

    /**
     * Construttore.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function supportsVectorSearch(): bool;

    // abstract public function createIndex(Model $model): void;

    /**
     * Verifica se l'indice esiste.
     */
    abstract protected function checkIndexExists(Model $model): bool;

    // abstract public function search(string $query, array $options = []): array;

    abstract public function vectorSearch(array $vector, array $options = []): array;

    abstract public function buildSearchFilters(array $filters): array|string;

    abstract public function reindexModel(string $modelClass): void;

    // /**
    //  * Determina se il modello è searchable
    //  */
    // public function isSearchable(Model $model): bool
    // {
    //     return $this->usesSearchableTrait($model);
    // }

    // /**
    //  * Verifica se il modello usa uno dei trait Searchable
    //  */
    // protected function usesSearchableTrait(Model $model): bool
    // {
    //     return in_array(Searchable::class, class_uses_recursive($model)) ||
    //         in_array(ScoutSearchable::class, class_uses_recursive($model));
    // }

    // /**
    //  * Verifica che il modello sia searchable prima di eseguire operazioni
    //  */
    // public function ensureIsSearchable(Model $model): void
    // {
    //     if (!$this->isSearchable($model)) {
    //         throw new \InvalidArgumentException(
    //             sprintf('Model %s does not implement a supported Searchable trait', $model::class)
    //         );
    //     }
    // }

    final public function checkIndex(Model $model, bool $createIfMissing = false): bool
    {
        // $this->ensureIsSearchable($model);

        try {
            if (method_exists($model, 'checkIndex')) {
                return $model->checkIndex($createIfMissing);
            }

            // Implementazione predefinita se il metodo non esiste nel modello
            $result = $this->checkIndexExists($model);

            if (! $result && $createIfMissing) {
                $this->createIndex($model);

                return true;
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Error checking index', [
                'model' => $model::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
