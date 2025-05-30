<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Elastic\ScoutDriverPlus\Searchable as ScoutSearchable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Search\Jobs\IndexInSearchJob;

/**
 * Extended searchable trait that supports multiple engines
 * Provides enhanced functionality for Elasticsearch and Typesense.
 *
 * Note: Models using this trait should implement ISearchable
 */
trait Searchable
{
    use ScoutSearchable {
        searchable as protected baseSearchable;
        searchableSync as protected baseSearchableSync;
    }

    /**
     * Field name for indexing timestamp.
     */
    public const INDEXED_AT_FIELD = 'indexed_at';

    public function searchable(): void
    {
        if (config('scout.vector_search.enabled')) {
            $engine = $this->searchableUsing();

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                Bus::chain([
                    new GenerateEmbeddingsJob($this),
                    new IndexInSearchJob($this),
                ])->dispatch();

                return;
            }
        }

        $this->baseSearchable();
    }

    public function searchableSync(): void
    {
        if (config('scout.vector_search.enabled')) {
            $engine = $this->searchableUsing();

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                new GenerateEmbeddingsJob($this)->dispatchSync();
            }
        }

        $this->baseSearchableSync();
    }

    /**
     * Extends the standard method with support for embeddings and other data.
     */
    public function toSearchableArray(): array
    {
        $array = parent::toSearchableArray();
        $engine = $this->searchableUsing();

        $array['id'] = $this->getKey();
        $array['connection'] = $this->getConnectionName() ?: 'default';
        $array['entity'] = $this->getTable();
        $array[self::INDEXED_AT_FIELD] = now()->utc()->toZuluString();

        // Add embeddings if available
        if (config('scout.vector_search.enabled') && $engine instanceof ISearchEngine && $engine->supportsVectorSearch() && method_exists($this, 'embeddings')) {
            $embeddings = $this->embeddings()->get()->pluck('embedding')->toArray();

            if ($embeddings !== []) {
                $array['embedding'] = $embeddings[0] ?? []; // Use first embedding
            }
        }

        return $array;
    }

    /**
     * Prepare text data for embedding generation.
     */
    public function prepareDataToEmbed(): ?string
    {
        if (! isset($this->embed) || $this->embed === []) {
            return null;
        }

        $data = '';

        foreach ($this->embed as $attribute) {
            $value = $this->{$attribute};

            if ($this->isValidEmbedValue($value)) {
                $data .= ' ' . $value;
            }
        }

        return $data;
    }

    /**
     * Relationship with model embeddings.
     */
    public function embeddings(): MorphMany
    {
        return $this->morphMany(ModelEmbedding::class, 'model');
    }

    /**
     * Get field mapping for search engine
     * Convert generic field definitions to the format required by the current search engine.
     *
     * @return array Mapping in format appropriate for current search engine
     */
    public function getSearchMapping(): array
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            return $engine->getSearchMapping($this);
        }

        return [];
    }

    /**
     * Reindex all records of this model.
     */
    public function reindex(): void
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            $engine->reindex(get_class($this));
        }
    }

    /**
     * Check if index exists and create if needed.
     */
    public function checkIndex(): bool
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            return $engine->checkIndex($this);
        }

        return false;
    }

    /**
     * Check if index exists and create if needed.
     */
    public function ensureIndex(): bool
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            return $engine->ensureIndex($this);
        }

        return false;
    }

    /**
     * Create or update search index.
     */
    public function createIndex(): void
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            $engine->createIndex($this);
        }
    }

    /**
     * Get the timestamp of last indexing.
     */
    public function getLastIndexedTimestamp(): ?string
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            return $engine->getLastIndexedTimestamp($this);
        }

        return null;
    }

    /**
     * Perform vector search based on embedding.
     *
     * @param  array  $vector  Embedding vector
     * @param  array  $options  Search options
     */
    public function vectorSearch(array $vector, array $options = []): Collection
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
            return $engine->vectorSearch($vector, array_merge($options, [
                'index' => $this->searchableAs(),
            ]));
        }

        return collect();
    }

    /**
     * Check if a value is valid for embedding.
     */
    private function isValidEmbedValue(mixed $value): bool
    {
        return $value
            && is_string($value)
            && $value !== ''
            && $value !== '0';
    }
}
