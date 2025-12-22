<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Override;

trait CommonEngineFunctions
{
    public const string INDEXED_AT_FIELD = '_indexed_at';

    /**
     * @param  Model&Searchable  $model
     *
     * @throws \Http\Client\Exception
     */
    abstract public function checkIndex(Model $model): bool;

    public function getName(): string
    {
        return Str::of(static::class)->afterLast('\\')->replace('Engine', '')->toString();
    }

    public function isVectorSearch(Builder $builder): bool
    {
        return isset($builder->wheres['vector'])
            || isset($builder->wheres['embedding'])
            || method_exists($builder->model, 'getVectorField');
    }

    #[Override]
    public function prepareDataToEmbed(Model $model): ?string
    {
        if (! method_exists($model, 'prepareDataToEmbed')) {
            return null;
        }

        return $model->prepareDataToEmbed();
    }

    /**
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    #[Override]
    public function ensureIndex(string|Model $model): bool
    {
        if (! $this->checkIndex($model)) {
            /** @var Model&Searchable $model */
            $this->createIndex($model);

            return true;
        }

        return false;
    }

    #[Override]
    public function ensureSearchable(Model $model): void
    {
        throw_unless($this->usesSearchableTrait($model), InvalidArgumentException::class, 'Model ' . $model::class . ' does not implement the Searchable trait');
    }

    /**
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function getLastIndexedTimestamp(Model $model): ?string
    {
        $this->ensureIndex($model);

        try {
            /** @var Model&Searchable $model */
            return $model::search('*')
                ->where('entity', $model->getTable())
                ->orderBy(self::INDEXED_AT_FIELD, 'desc')
                ->take(1)
                ->get()
                ->first()
                ?->{self::INDEXED_AT_FIELD};
        } catch (Exception $exception) {
            Log::error('Error getting last indexed timestamp from Typesense', [
                'index' => $model->searchableAs(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if model uses the Searchable trait.
     */
    protected function usesSearchableTrait(Model $model): bool
    {
        return in_array(Searchable::class, class_uses_recursive($model), true);
    }

    /**
     * @param  string|Model&Searchable|class-string<Model>  $name
     * @return array{model:Model,collection:string}|null
     */
    private function matchModelToCollectionName(string|Model $name): ?array
    {
        $model = null;
        $collection = null;

        if ($name instanceof Model) {
            $model = $name;
            $collection = $model->searchableAs();
        } elseif (is_string($name) && class_exists($name)) {
            $model = new $name();
            $collection = $model->searchableAs();
        } elseif (is_string($name)) {
            $models = models(true, filter: fn (string $model): bool => class_uses_trait($model, Searchable::class) && new $model()->searchableAs() === $name);

            if (count($models) > 1) {
                throw new Exception('Multiple models found for collection name: ' . $name);
            }

            $model = head($models);
            $collection = $name;
        }

        if ($collection === null) {
            throw new Exception('Unable to resolve collection name for index creation.');
        }

        // Get mapping from the model if available; otherwise fail
        if ($model === null) {
            return null;
        }

        return [
            'model' => $model,
            'collection' => $collection,
        ];
    }

    private function extractVectorFromBuilder(Builder $builder): array
    {
        return $builder->wheres['vector'] ?? $builder->wheres['embedding'] ?? [];
    }
}
