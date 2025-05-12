<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Interface that defines extended methods for searchable models
 * Extends the base functionality of Laravel Scout.
 */
interface ISearchable
{
    /**
     * Get the search mapping schema for the search engine.
     */
    public function getSearchMapping(): array;

    /**
     * Prepare data for embedding (if supported).
     */
    public function prepareDataToEmbed(): ?string;

    /**
     * Start a complete reindexing for this model.
     */
    public function reindex(): void;

    /**
     * Check the index and create it if necessary.
     */
    public function checkIndex(bool $createIfMissing = false): bool;

    /**
     * Create or update the index for this model.
     */
    public function createIndex(): void;

    /**
     * Get the timestamp of the last indexing.
     */
    public function getLastIndexedTimestamp(): ?string;

    /**
     * Perform vector search with embedding.
     */
    public function vectorSearch(array $vector, array $options = []): mixed;
}
