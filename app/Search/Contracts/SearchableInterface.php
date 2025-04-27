<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Interface that defines extended methods for searchable models
 * Extends the base functionality of Laravel Scout
 */
interface SearchableInterface
{
    /**
     * Get the search mapping schema for the search engine
     * 
     * @return array
     */
    public function getSearchMapping(): array;

    /**
     * Prepare data for embedding (if supported)
     * 
     * @return string|null
     */
    public function prepareDataToEmbed(): ?string;

    /**
     * Start a complete reindexing for this model
     */
    public function reindex(): void;

    /**
     * Check the index and create it if necessary
     * 
     * @param bool $createIfMissing
     * @return bool
     */
    public function checkIndex(bool $createIfMissing = false): bool;

    /**
     * Create or update the index for this model
     */
    public function createIndex(): void;

    /**
     * Get the timestamp of the last indexing
     * 
     * @return string|null
     */
    public function getLastIndexedTimestamp(): ?string;

    /**
     * Perform vector search with embedding
     * 
     * @param array $vector
     * @param array $options
     * @return mixed
     */
    public function vectorSearch(array $vector, array $options = []);
}
