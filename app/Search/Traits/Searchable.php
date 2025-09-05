<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Elastic\ScoutDriverPlus\Searchable as ScoutSearchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Bus;
use Laravel\Scout\Scout;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;

/**
 * Extended searchable trait that supports multiple engines
 * Provides enhanced functionality for Elasticsearch and Typesense.
 */
trait Searchable
{
    use ScoutSearchable {
        queueMakeSearchable as protected baseQueueMakeSearchable;
        syncMakeSearchable as protected baseSyncMakeSearchableSync;
        searchableConnection as protected baseSearchableConnection;
    }

    /**
     * Field name for indexing timestamp.
     */
    public static string $indexedAtField = 'indexed_at';

    private ?string $cacheConnection = null;

    public function setCacheConnection(string $connection): void
    {
        $this->cacheConnection = $connection;
    }

    public function searchableConnection(): ?string
    {
        return $this->cacheConnection;
    }

    /**
     * Reindex all records of this model.
     */
    public static function reindex(?int $chunk = null): void
    {
        static::makeAllSearchable($chunk);
    }

    public function queueMakeSearchable($models): void
    {
        if (config('scout.vector_search.enabled')) {
            $engine = $this->searchableUsing();

            if (!is_iterable($models)) {
                $models = collect([$models]);
            }

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                Bus::chain([
                    ...$models->map(fn(Model $model) => new GenerateEmbeddingsJob($model))->toArray(),
                    new Scout::$makeSearchableJob($models),
                ])->dispatch()
                    ->onQueue($models->first()->syncWithSearchUsingQueue())
                    ->onConnection($models->first()->syncWithSearchUsing());

                return;
            }
        }

        $this->baseQueueMakeSearchable($models);
    }

    public function syncMakeSearchable($models): void
    {
        if (config('scout.vector_search.enabled')) {
            $engine = $this->searchableUsing();

            if (!is_iterable($models)) {
                $models = collect([$models]);
            }

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                foreach ($models as $model) {
                    dispatch(new GenerateEmbeddingsJob($model)
                        ->onQueue($model->syncWithSearchUsingQueue())
                        ->onConnection($model->syncWithSearchUsing()));
                }
            }
        }

        $this->baseSyncMakeSearchableSync($models);
    }

    /**
     * Extends the standard method with support for embeddings and other data.
     */
    public function toSearchableArray(): array
    {
        $engine = $this->searchableUsing();

        $array = [
            'id' => $this->getKey(),
            'connection' => $this->getConnectionName() ?: 'default',
            'entity' => $this->getTable(),
            self::$indexedAtField => now()->utc()->toIso8601ZuluString(),
        ];

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
     * Check if the index exists and create if needed.
     */
    public function ensureIndexExists(): bool
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            return $engine->ensureIndex($this);
        }

        if (method_exists($engine, 'createIndex')) {
            // Use Scout's native method if available.
            if (! method_exists($engine, 'indexExists') || ! $engine->indexExists($this)) {
                $engine->createIndex($this);

                return true;
            }
        }

        return false;
    }

    /**
     * Create or update the index.
     */
    public function createIndex(): void
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof ISearchEngine) {
            $engine->createIndex($this);
        } elseif (method_exists($engine, 'createIndex')) {
            // Use Scout's native method.
            $engine->createIndex($this->searchableAs());
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
