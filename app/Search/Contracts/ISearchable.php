<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface that defines extended methods for searchable models
 * Extends the base functionality of Laravel Scout.
 */
interface ISearchable
{
    public const string INDEXED_AT_FIELD = '_indexed_at';

    /**
     * Sync documents modified after the last indexing.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int;

    /**
     * Transform filters to a format suitable for the search engine.
     */
    public function buildSearchFilters(array $filters): array|string;

    /**
     * Get the search mapping schema for the search engine.
     *
     * @param  Model  $model
     */
    public function getSearchMapping(Model $model): array;

    /**
     * Prepare data for embedding (if supported).
     *
     * @param  Model  $model
     */
    public function prepareDataToEmbed(Model $model): ?string;

    /**
     * Start a complete reindexing for this model.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function reindex(string $modelClass): void;

    public function ensureSearchable(Model $model): void;

    /**
     * Check if the index exists.
     *
     * @param  Model  $model
     */
    public function checkIndex(Model $model): bool;

    /**
     * Check if the index exists.
     *
     * @param  Model  $model
     */
    public function ensureIndex(Model $model): bool;

    /**
     * Get the timestamp of the last indexing.
     *
     * @param  Model  $model
     */
    public function getLastIndexedTimestamp(Model $model): ?string;
}
