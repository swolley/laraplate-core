<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface che definisce le operazioni supportate dai motori di ricerca.
 */
interface ISearchEngine
{
    /**
     * Verifica se il motore supporta la ricerca vettoriale.
     */
    public function supportsVectorSearch(): bool;

    /**
     * Verifica se un indice esiste e la sua struttura è corretta.
     */
    public function checkIndex(Model $model, bool $createIfMissing = false): bool;

    /**
     * Crea o aggiorna un indice per il modello specificato.
     */
    public function createIndex(Model $model): void;

    /**
     * Indicizza un singolo documento.
     */
    public function indexDocument(Model $model): void;

    /**
     * Indicizza un documento con supporto per embedding vettoriali.
     */
    public function indexDocumentWithEmbedding(Model $model): void;

    /**
     * Rimuove un documento dall'indice.
     */
    public function deleteDocument(Model $model): void;

    /**
     * Indicizza un gruppo di documenti in modalità bulk.
     */
    public function bulkIndex(iterable $models): void;

    /**
     * Esegue una ricerca full-text.
     *
     * @param  string  $query  Query di ricerca
     * @param  array  $options  Opzioni di ricerca (filtri, campi, ordinamento, ecc.)
     * @return array Risultati della ricerca
     */
    public function search(string $query, array $options = []): array;

    /**
     * Esegue una ricerca vettoriale basata su embedding.
     *
     * @param  array  $vector  Array di valori dell'embedding
     * @param  array  $options  Opzioni di ricerca (filtri, ordinamento, ecc.)
     * @return array Risultati della ricerca
     */
    public function vectorSearch(array $vector, array $options = []): array;

    /**
     * Reindicizza tutti i documenti di un modello.
     */
    public function reindexModel(string $modelClass): void;

    /**
     * Sincronizza i documenti modificati dopo l'ultima indicizzazione.
     */
    public function syncModel(string $modelClass, ?int $id = null, ?string $from = null): int;

    /**
     * Trasforma i filtri in un formato adatto al motore di ricerca.
     */
    public function buildSearchFilters(array $filters): array|string;
}
