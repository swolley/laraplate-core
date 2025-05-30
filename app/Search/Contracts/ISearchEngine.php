<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Interface that defines methods for search engines
 * Extends ISearchable to include engine-specific functionality.
 */
interface ISearchEngine extends ISearchable, ISearchAnalytics
{
    /**
     * Check if the engine supports vector search.
     */
    public function supportsVectorSearch(): bool;

    /**
     * Delete a document from the index.
     *
     * @param  Model|Collection<int,Model>  $model
     */
    public function delete(Model|Collection $model): void;

    /**
     * Index a single document.
     *
     * @param  Model|Collection<int,Model>  $model
     */
    public function index(Model|Collection $model): void;

    /**
     * Index a document with vector search support.
     *
     * @param  Model|Collection<int,Model>  $model
     */
    public function indexWithEmbedding(Model|Collection $model): void;

    // /**
    //  * Index a group of documents in bulk mode.
    //  */
    // public function bulkIndex(iterable $models): void;

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
}
