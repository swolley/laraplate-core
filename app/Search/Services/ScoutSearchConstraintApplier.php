<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;

final readonly class ScoutSearchConstraintApplier
{
    public const string FILTER_ERROR = 'Search filters must be applied by the search engine to keep pagination consistent.';

    public const string SORT_ERROR = 'Search sort must be applied by the search engine to keep pagination consistent.';

    /**
     * @param  array<int, \Modules\Core\Casts\Sort>  $sort
     */
    public function apply(mixed $builder, Model $model, ?FiltersGroup $filters = null, array $sort = []): void
    {
        if ($filters instanceof FiltersGroup) {
            $this->applyFilters($builder, $filters, $model);
        }

        foreach ($sort as $item) {
            $field = $this->fieldName($model, $item->property);

            if ($field === null) {
                throw new InvalidArgumentException(self::SORT_ERROR);
            }

            $builder->orderBy($field, $item->direction->value);
        }
    }

    private function applyFilters(mixed $builder, FiltersGroup $filters, Model $model): void
    {
        if ($filters->operator !== WhereClause::And) {
            throw new InvalidArgumentException(self::FILTER_ERROR);
        }

        foreach ($filters->filters as $filter) {
            if ($filter instanceof FiltersGroup) {
                $this->applyFilters($builder, $filter, $model);

                continue;
            }

            $this->applyFilter($builder, $filter, $model);
        }
    }

    private function applyFilter(mixed $builder, Filter $filter, Model $model): void
    {
        $field = $this->fieldName($model, $filter->property);

        if ($field === null) {
            throw new InvalidArgumentException(self::FILTER_ERROR);
        }

        match ($filter->operator) {
            FilterOperator::Equals => $builder->where($field, $filter->value),
            FilterOperator::In => $builder->whereIn($field, is_array($filter->value) ? $filter->value : [$filter->value]),
            FilterOperator::NotEquals => $builder->whereNotIn($field, is_array($filter->value) ? $filter->value : [$filter->value]),
            default => throw new InvalidArgumentException(self::FILTER_ERROR),
        };
    }

    private function fieldName(Model $model, string $property): ?string
    {
        $table_prefix = $model->getTable() . '.';

        if (str_starts_with($property, $table_prefix)) {
            return mb_substr($property, mb_strlen($table_prefix));
        }

        if (str_contains($property, '.')) {
            return null;
        }

        return $property;
    }
}
