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

    #[Override]
    /**
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    public function ensureIndex(Model $model): bool
    {
        $this->ensureSearchable($model);

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

    private function extractVectorFromBuilder(Builder $builder): array
    {
        return $builder->wheres['vector'] ?? $builder->wheres['embedding'] ?? [];
    }
}
