<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Elastic\ScoutDriver\Engine;
use Elastic\ScoutDriverPlus\Searchable as ScoutSearchable;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Bus;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Laravel\Scout\Scout;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
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
        if (config('scout.vector_search.enabled')) {
            if (! is_iterable($models)) {
                $models = collect([$models]);
            }

            if (! config('scout.queue')) {
                $this->syncMakeSearchable($models);

                return;
            }

            $engine = $this->searchableUsing();

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                Bus::chain([
                    ...$models->map(fn (Model $model): GenerateEmbeddingsJob => new GenerateEmbeddingsJob($model))->toArray(),
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

            if (! is_iterable($models)) {
                $models = collect([$models]);
            }

            if ($engine instanceof ISearchEngine && $engine->supportsVectorSearch()) {
                foreach ($models as $model) {
                    // If Scout queue is disabled, run embeddings job synchronously
                    if (! config('scout.queue')) {
                        new GenerateEmbeddingsJob($model)->handle();
                    } else {
                        dispatch(new GenerateEmbeddingsJob($model)
                            ->onQueue($model->syncWithSearchUsingQueue())
                            ->onConnection($model->syncWithSearchUsing()));
                    }
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

        if (class_uses_trait($this, HasValidity::class)) {
            $array['valid_from'] = $this->{HasValidity::validFromKey()};
            $array['valid_to'] = $this->{HasValidity::validToKey()};
        }

        if (class_uses_trait($this, SoftDeletes::class)) {
            $array['is_deleted'] = $this->is_deleted;
        }

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
    public function getSearchMapping(?SchemaDefinition $schema = null): array
    {
        if (! $schema instanceof SchemaDefinition) {
            $schema = $this->getSchemaDefinition();
            // $table = Inspect::table($this->getTable());

            // $table->columns->each(function (Column $column) use ($schema) {
            //     $schema->addField(new FieldDefinition($column->name, FieldType::fromDoctrine($column->type)));
            // });

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

        if ($engine instanceof Engine) {
            $engineName = 'elasticsearch';
        } elseif ($engine instanceof TypesenseEngine) {
            $engineName = 'typesense';
        } elseif ($engine instanceof DatabaseEngine) {
            $engineName = 'database';
        } else {
            throw new Exception('Unsupported engine ' . $engine::class);
        }

        $schemaManager = app(SchemaManager::class);

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

        // Use Scout's native method if available.
        if (method_exists($engine, 'createIndex') && (! method_exists($engine, 'indexExists') || ! $engine->checkIndex($this))) {
            $engine->createIndex($this);

            return true;
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
