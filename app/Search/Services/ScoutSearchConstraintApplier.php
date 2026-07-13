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
        if ($filters->operator === WhereClause::Or) {
            $this->appendAdvancedFilter($builder, $this->normalizeFiltersGroup($filters, $model));

            return;
        }

        foreach ($filters->filters as $filter) {
            if ($filter instanceof FiltersGroup) {
                if ($filter->operator === WhereClause::Or) {
                    $this->appendAdvancedFilter($builder, $this->normalizeFiltersGroup($filter, $model));

                    continue;
                }

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
            FilterOperator::Great,
            FilterOperator::GreatEquals,
            FilterOperator::Less,
            FilterOperator::LessEquals,
            FilterOperator::Between => $this->appendAdvancedFilter($builder, $this->normalizeFilter($filter, $model)),
            default => throw new InvalidArgumentException(self::FILTER_ERROR),
        };
    }

    /**
     * @return array{operator: string, filters: list<array<string, mixed>>}
     */
    private function normalizeFiltersGroup(FiltersGroup $filters, Model $model): array
    {
        $out = [];

        foreach ($filters->filters as $filter) {
            $out[] = $filter instanceof FiltersGroup
                ? $this->normalizeFiltersGroup($filter, $model)
                : $this->normalizeFilter($filter, $model);
        }

        return [
            'operator' => $filters->operator->value,
            'filters' => $out,
        ];
    }

    /**
     * @return array{field: string, operator: string, value: mixed}
     */
    private function normalizeFilter(Filter $filter, Model $model): array
    {
        $field = $this->fieldName($model, $filter->property);

        if ($field === null) {
            throw new InvalidArgumentException(self::FILTER_ERROR);
        }

        if (in_array($filter->operator, [FilterOperator::Like, FilterOperator::NotLike], true)) {
            throw new InvalidArgumentException(self::FILTER_ERROR);
        }

        if ($filter->operator === FilterOperator::Between && (! is_array($filter->value) || count($filter->value) !== 2)) {
            throw new InvalidArgumentException(self::FILTER_ERROR);
        }

        return [
            'field' => $field,
            'operator' => $filter->operator->value,
            'value' => $filter->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function appendAdvancedFilter(mixed $builder, array $filter): void
    {
        $existing = is_array($builder->options['advanced_filters'] ?? null)
            ? $builder->options['advanced_filters']
            : ['operator' => WhereClause::And->value, 'filters' => []];

        $existing['filters'][] = $filter;
        $builder->options['advanced_filters'] = $existing;

        $this->attachEloquentAdvancedFilterCallback($builder);
    }

    private function attachEloquentAdvancedFilterCallback(mixed $builder): void
    {
        if (! method_exists($builder, 'query') || ($builder->options['_advanced_filters_query_attached'] ?? false) === true) {
            return;
        }

        $existing_callback = $builder->queryCallback ?? null;

        $builder->query(static function (mixed $query) use ($builder, $existing_callback): void {
            if ($existing_callback !== null) {
                $existing_callback($query);
            }

            $advanced_filters = $builder->options['advanced_filters'] ?? null;

            if (is_array($advanced_filters)) {
                self::applyAdvancedFiltersToEloquent($query, $advanced_filters);
            }
        });

        $builder->options['_advanced_filters_query_attached'] = true;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private static function applyAdvancedFiltersToEloquent(mixed $query, array $group): void
    {
        self::applyAdvancedFiltersGroupToEloquent($query, $group);
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private static function applyAdvancedFiltersGroupToEloquent(mixed $query, array $group, string $boolean = 'and'): void
    {
        $method = $boolean === WhereClause::Or->value ? 'orWhere' : 'where';

        $query->{$method}(static function (mixed $nested_query) use ($group): void {
            $child_boolean = ($group['operator'] ?? WhereClause::And->value) === WhereClause::Or->value
                ? WhereClause::Or->value
                : WhereClause::And->value;

            foreach (($group['filters'] ?? []) as $filter) {
                if (! is_array($filter)) {
                    continue;
                }

                if (isset($filter['filters'])) {
                    self::applyAdvancedFiltersGroupToEloquent($nested_query, $filter, $child_boolean);

                    continue;
                }

                self::applyAdvancedFilterToEloquent($nested_query, $filter, $child_boolean);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private static function applyAdvancedFilterToEloquent(mixed $query, array $filter, string $boolean): void
    {
        $field = (string) ($filter['field'] ?? '');
        $operator = (string) ($filter['operator'] ?? '');
        $value = $filter['value'] ?? null;
        $where = $boolean === WhereClause::Or->value ? 'orWhere' : 'where';
        $where_in = $boolean === WhereClause::Or->value ? 'orWhereIn' : 'whereIn';
        $where_not_in = $boolean === WhereClause::Or->value ? 'orWhereNotIn' : 'whereNotIn';
        $where_between = $boolean === WhereClause::Or->value ? 'orWhereBetween' : 'whereBetween';

        match ($operator) {
            FilterOperator::Equals->value => $query->{$where}($field, '=', $value),
            FilterOperator::In->value => $query->{$where_in}($field, is_array($value) ? $value : [$value]),
            FilterOperator::NotEquals->value => $query->{$where_not_in}($field, is_array($value) ? $value : [$value]),
            FilterOperator::Great->value,
            FilterOperator::GreatEquals->value,
            FilterOperator::Less->value,
            FilterOperator::LessEquals->value => $query->{$where}($field, $operator, $value),
            FilterOperator::Between->value => $query->{$where_between}($field, is_array($value) ? array_values($value) : [$value, $value]),
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
