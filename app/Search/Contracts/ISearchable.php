<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Engines\Engine;

/**
 * Interface that defines extended methods for searchable models
 * Extends the base functionality of Laravel Scout.
 */
interface ISearchable extends Engine
{
    /**
     * Get the search mapping schema for the search engine.
     */
    public function getSearchMapping(Model $model): array;

    /**
     * Prepare data for embedding (if supported).
     */
    public function prepareDataToEmbed(): ?string;

    /**
     * Start a complete reindexing for this model.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function reindex(string $modelClass): void;

    /**
     * Check if the index exists.
     */
    public function checkIndex(Model $model): bool;

    /**
     * Check if the index exists.
     */
    public function ensureIndex(Model $model): bool;

    /**
     * Get the timestamp of the last indexing.
     */
    public function getLastIndexedTimestamp(Model $model): ?string;

    /**
     * Perform vector search with embedding.
     *
     * @return mixed
     */
    public function vectorSearch(array $vector, array $options = []);
}
