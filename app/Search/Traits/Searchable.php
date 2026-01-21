<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Elastic\ScoutDriver\Engine as ElasticEngine;
use Elastic\ScoutDriverPlus\Searchable as ElasticScoutSearchable;
use Exception;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Helpers\HasTranslations;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;
use Modules\Core\Search\Schema\SchemaManager;

/**
 * Extended searchable trait that supports multiple engines
 * Provides enhanced functionality for Elasticsearch and Typesense.
 */
trait Searchable
{
    use ElasticScoutSearchable {
        queueMakeSearchable as private baseQueueMakeSearchable;
        syncMakeSearchable as private baseSyncMakeSearchableSync;
        searchableConnection as private baseSearchableConnection;
    }

    /**
     * Field name for indexing timestamp.
     */
    public static string $indexedAtField = '_indexed_at';

    private ?string $cacheConnection = null;

    /**
     * Reindex all records of this model.
     */
    public static function reindex(?int $chunk = null): void
    {
        static::makeAllSearchable($chunk);
    }

    public function setCacheConnection(string $connection): void
    {
        $this->cacheConnection = $connection;
    }

    public function searchableConnection(): ?string
    {
        return $this->cacheConnection;
    }

    public function queueMakeSearchable($models): void
    {
        $this->ensureIndexesForModels($models);

        if (! is_iterable($models)) {
            $models = collect([$models]);
        }

        $sync = ! config('scout.queue');

        foreach ($models as $model) {
            // Emit event instead of calling job directly
            // Listeners will handle pre-processing (embeddings, translations, etc.)
            // and finalize listener will dispatch IndexInSearchJob when all are completed
            $event = new ModelRequiresIndexing($model, $sync);
            event($event);

            // Save event in cache for the finalize listener
            if (! $sync) {
                $cache_key = "model_indexing:{$model->getTable()}:{$model->getKey()}";
                \Illuminate\Support\Facades\Cache::put($cache_key, $event, now()->addMinutes(10));
            }
        }

        // If sync mode, the finalize listener will handle everything synchronously
        // Otherwise, events will be handled by listeners asynchronously
        if ($sync) {
            // In sync mode, we still need to call base method for immediate indexing
            // if no pre-processing is required
            $this->baseQueueMakeSearchable($models);
        }
    }

    public function syncMakeSearchable($models): void
    {
        $this->ensureIndexesForModels($models);

        if (! is_iterable($models)) {
            $models = collect([$models]);
        }

        foreach ($models as $model) {
            // Emit event for sync mode
            $event = new ModelRequiresIndexing($model, true);
            event($event);

            // In sync mode, listeners will handle everything synchronously
            // The finalize listener will dispatch IndexInSearchJob immediately
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
            'id' => (string) $this->getKey(),
            'connection' => $this->getConnectionName() ?: 'default',
            'entity' => $this->getTable(),
            self::$indexedAtField => now()->utc()->toIso8601ZuluString(),
        ];

        if (class_uses_trait($this, HasValidity::class)) {
            $array['valid_from'] = $this->{HasValidity::validFromKey()};
            $array['valid_to'] = $this->{HasValidity::validToKey()};
        }

        if (class_uses_trait($this, SoftDeletes::class)) {
            $array['is_deleted'] = $this->{self::getIsDeletedColumn()};
        }

        // Add embeddings if available
        if ($this->vectorSearchEnabled() && $engine instanceof ISearchEngine && $engine->supportsVectorSearch() && method_exists($this, 'embeddings')) {
            $embeddings = $this->embeddings()->get()->pluck('embedding')->toArray();

            if ($embeddings !== []) {
                $array['embedding'] = $embeddings[0] ?? []; // Use first embedding
            }
        }

        return $array;
    }

    /**
     * Prepare text data for embedding generation.
     * If the model has translations, concatenate all translations for multilingual embedding.
     */
    public function prepareDataToEmbed(): ?string
    {
        if (! isset($this->embed) || $this->embed === []) {
            return null;
        }

        $data = '';

        // If the model has translations, concatenate all translations for multilingual embedding
        if (class_uses_trait($this, HasTranslations::class)) {
            $available_locales = LocaleContext::getAvailable();

            foreach ($available_locales as $locale) {
                $translation = $this->getTranslation($locale);

                if ($translation) {
                    foreach ($this->embed as $attribute) {
                        $value = $translation->{$attribute} ?? null;

                        if ($this->isValidEmbedValue($value)) {
                            $data .= ' ' . $value;
                        }
                    }
                }
            }
        } else {
            // If no translations, use direct values (current behavior)
            foreach ($this->embed as $attribute) {
                $value = $this->{$attribute};

                if ($this->isValidEmbedValue($value)) {
                    $data .= ' ' . $value;
                }
            }
        }

        return mb_trim($data);
    }

    /**
     * Relationship with model embeddings.
     * ModelEmbedding is in Core (structure), AI module handles generation/population.
     */
    public function embeddings(): MorphMany
    {
        return $this->morphMany(\Modules\Core\Models\ModelEmbedding::class, 'model');
    }

    /**
     * Get field mapping for search engine
     * Convert generic field definitions to the format required by the current search engine.
     */
    public function getSearchMapping(?SchemaDefinition $schema = null): array
    {
        if (! $schema instanceof SchemaDefinition) {
            $schema = $this->getSchemaDefinition();
            $document = $this->toSearchableArray();

            foreach ($document as $key => $value) {
                if ($key === 'embedding') {
                    $schema->addField(new FieldDefinition($key, FieldType::VECTOR, [IndexType::SEARCHABLE, IndexType::VECTOR]));
                } else {
                    $schema->addField(new FieldDefinition($key, FieldType::fromValue($value), [IndexType::SEARCHABLE]));
                }
            }
        }

        // Get the current engine and translate
        $engine = $this->searchableUsing();

        if ($engine instanceof ElasticEngine) {
            $engineName = 'elasticsearch';
        } elseif ($engine instanceof TypesenseEngine) {
            $engineName = 'typesense';
        } elseif ($engine instanceof DatabaseEngine) {
            $engineName = 'database';
        } else {
            throw new Exception('Unsupported engine ' . $engine::class);
        }

        $schemaManager = resolve(SchemaManager::class);

        return $schemaManager->translateForEngine($schema, $engineName);
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
            $needs_creation = true;

            if (is_callable([$engine, 'indexExists'])) {
                $needs_creation = ! (bool) call_user_func([$engine, 'indexExists'], $this->searchableAs());
            } elseif (is_callable([$engine, 'checkIndex'])) {
                $needs_creation = ! (bool) call_user_func([$engine, 'checkIndex'], $this);
            }

            if ($needs_creation) {
                call_user_func([$engine, 'createIndex'], $this);

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

    private function ensureIndexesForModels($models): void
    {
        if (! is_iterable($models)) {
            $this->ensureIndexExists();

            return;
        }

        $by_class = [];

        foreach ($models as $model) {
            $class = $model::class;

            if (isset($by_class[$class])) {
                continue;
            }

            $model->ensureIndexExists();

            $by_class[$class] = $model;
        }
    }

    private function vectorSearchEnabled(): bool
    {
        /** @phpstan-ignore-next-line false-positive: config loaded via module */
        if (! Config::has('search.vector_search.enabled')) {
            return false;
        }

        /** @phpstan-ignore-next-line false-positive: config loaded via module */
        return (bool) Config::get('search.vector_search.enabled');
    }

    private function getSchemaDefinition(): SchemaDefinition
    {
        return new SchemaDefinition($this->getTable());
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
