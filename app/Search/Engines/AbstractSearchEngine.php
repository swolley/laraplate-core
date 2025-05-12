<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Engines\Engine;
use Modules\Core\Search\Contracts\ISearchEngine;

/**
 * Classe base astratta per i motori di ricerca.
 */
abstract class AbstractSearchEngine extends Engine implements ISearchEngine
{
    /**
     * Construttore.
     */
    public function __construct(
        /**
         * Configurazione del motore di ricerca.
         */
        protected array $config = [],
    ) {}

    abstract public function supportsVectorSearch(): bool;

    /**
     * Verifica se l'indice esiste.
     */
    abstract protected function checkIndexExists(Model $model): bool;

    // abstract public function search(string $query, array $options = []): array;

    abstract public function vectorSearch(array $vector, array $options = []): array;

    abstract public function buildSearchFilters(array $filters): array|string;

    abstract public function reindexModel(string $modelClass): void;

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
