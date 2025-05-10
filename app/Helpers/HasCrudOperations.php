<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\ListRequestData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Grids\Resources\ResponseBuilder;

trait HasCrudOperations
{
    private array $preparedQueries = [];

    protected function listByPagination(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        $query->skip($filters->from - 1)->take($filters->to - $filters->from + 1);

        $data = $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords($data->count())
            ->setCurrentPage($filters->page)
            ->setPagination($filters->pagination);

        return $data;
    }

    protected function listByFromTo(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        $query->skip($filters->from - 1);

        if ($filters->to !== null) {
            $query->take($filters->to - $filters->from + 1);
        }

        $data = $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords($data->count())
            ->setFrom($filters->from)
            ->setTo($filters->to);

        return $data;
    }

    protected function listByOthers(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        if (isset($filters->limit)) {
            $query->take($filters->take);
        }

        $data = $filters->count ? $totalRecords : $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords(is_numeric($data) ? $data : $data->count());

        return $data;
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        // FIXME: non ricordo più cosa doveva essere
        $queryKey = $this->getQueryKey($query, $filters);

        if (isset($this->preparedQueries[$queryKey])) {
            $query->mergeBindings($this->preparedQueries[$queryKey]);

            return;
        }

        $query->where(function ($q) use ($filters): void {
            foreach ($filters as $field => $filter) {
                if (!isset($filter['value'])) {
                    continue;
                }
                if ($filter['value'] === '') {
                    continue;
                }
                $this->applyFilter($q, $field, $filter);
            }
        });

        $this->preparedQueries[$queryKey] = $query->getBindings();

        // Limita la dimensione della cache per evitare memory leaks
        if (count($this->preparedQueries) > 100) {
            array_shift($this->preparedQueries);
        }
    }

    /**
     * @param  array<int,array{field:string,direction?:'asc'|'desc'}>  $sorts
     */
    protected function applySorting(Builder $query, array $sorts): void
    {
        foreach ($sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction'] ?? 'asc');
        }
    }

    protected function getCacheKey(Model $model, array $params): string
    {
        return sprintf(
            'crud:%s:%s:%s',
            $model->getTable(),
            md5(json_encode($params)),
            auth()->id ?? 'guest',
        );
    }

    /**
     * removes non fillable request values from model.
     *
     * @param  array<string,mixed>  $values
     * @return array<int,string>
     */
    private function removeNonFillableProperties(Model $model, array &$values): array
    {
        $fillables = $model->getFillable();
        $discarder_values = [];

        if ($fillables !== []) {
            foreach (array_keys($values) as $property) {
                if (in_array($property, $fillables, true)) {
                    continue;
                }
                $discarder_values[] = "Discarder '{$property}', because is not a fillable property";
                unset($values[$property]);
            }
        }

        return $discarder_values;
    }

    /**
     * @template TItem
     *
     * @param  Collection<int,TItem>  $data
     * @param  array<int,string>  $groupBy
     */
    private function applyGroupBy(Collection &$data, array $groupBy): Collection
    {
        if ($groupBy === []) {
            return $data;
        }

        /** @psalm-suppress InvalidTemplateParam */
        return $data->groupBy($groupBy);
    }

    private function applyFilter(Builder $query, string $field, array $filter): void
    {
        $value = $filter['value'];
        $operator = $filter['operator'] ?? '=';

        match ($operator) {
            'like' => $query->where($field, 'like', "%{$value}%"),
            'in' => $query->whereIn($field, (array) $value),
            'between' => $query->whereBetween($field, (array) $value),
            default => $query->where($field, $operator, $value),
        };
    }
}
