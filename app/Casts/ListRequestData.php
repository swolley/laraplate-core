<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Services\PerModelSettingResolver;

class ListRequestData extends SelectRequestData
{
    public protected(set) int $pagination;

    public protected(set) ?int $page = null;

    public protected(set) ?int $skip = null;

    public protected(set) ?int $take = null;

    public protected(set) ?int $from = null;

    public protected(set) ?int $to = null;

    public protected(set) ?int $limit;

    public protected(set) bool $count;

    /**
     * @var array<int, Sort>
     */
    public protected(set) array $sort;

    public protected(set) ?FiltersGroup $filters;

    /**
     * @var array<int, string>
     */
    public protected(set) array $group_by = [];

    /**
     * @param  string|array<string>  $primaryKey
     * @param  array<string, mixed>  $validated
     */
    public function __construct(ListRequest|GridRequest $request, string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey, $module);

        $this->extractPagination($validated);

        if (! isset($this->limit)) {
            $this->limit = self::intFromMixed($validated['limit'] ?? null, $this->pagination);
        }

        $this->count = isset($validated['count']) && (bool) $validated['count'];
        $this->sort = $this->conformSorts(self::sortInput($validated['sort'] ?? []));
        $this->relations = $this->conformRelations(self::stringList($validated['relations'] ?? []));

        $filters = $validated['filters'] ?? null;

        if (is_array($filters)) {
            $this->filters = $this->parseFilters($filters);
        } else {
            $this->filters = null;
        }

        $group_by = self::stringList($validated['group_by'] ?? []);

        if ($group_by !== []) {
            $this->addGroupsToColumns($group_by);
            $this->group_by = array_map(
                static fn (string $group): string => preg_replace(sprintf('/^%s\./', $mainEntity), '', $group) ?? $group,
                $group_by,
            );
        }
    }

    public function calculateTotalPages(int $totalRecords): int
    {
        $per_page = max(1, $this->pagination);

        return (int) ceil($totalRecords / $per_page);
    }

    /**
     * Merge additional filters (e.g., ACL filters) with existing filters.
     *
     * If no existing filters, the new filters become the only filters.
     * If existing filters exist, they are wrapped with the new filters using AND operator.
     *
     * This ensures mandatory filters (like ACL restrictions) cannot be bypassed.
     *
     * @param  FiltersGroup  $filters  The filters to merge (typically ACL filters)
     */
    public function mergeFilters(FiltersGroup $filters): void
    {
        if (! $this->filters instanceof FiltersGroup) {
            $this->filters = $filters;

            return;
        }

        // Wrap existing filters with new filters using AND
        // Result: new_filters AND existing_filters
        $this->filters = new FiltersGroup(
            filters: [$filters, $this->filters],
            operator: WhereClause::And,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function extractPagination(array $validated): void
    {
        if (isset($validated['pagination']) || isset($validated['page'])) {
            $default_pagination = $this->getDefaultPagination();
            $this->take = self::intFromMixed($validated['pagination'] ?? null, $default_pagination);
            $this->pagination = self::intFromMixed($validated['pagination'] ?? null, $default_pagination);
            $this->page = self::intFromMixed($validated['page'] ?? null, 1);
            $this->skip = ($this->page - 1) * $this->pagination;
            $this->from = $this->skip + 1;
            $this->to = $this->from + $this->pagination;
        } elseif (isset($validated['from']) || isset($validated['to'])) {
            $this->from = self::intFromMixed($validated['from'] ?? null, 1);
            $this->skip = $this->from - 1;
            $this->to = array_key_exists('to', $validated) ? self::intFromMixed($validated['to'], 0) : null;

            if ($this->to !== null && $this->to !== 0) {
                $this->take = $this->to - $this->from;
                $this->pagination = $this->to - $this->from;
            }
        } elseif (isset($validated['limit'])) {
            $this->take = self::intFromMixed($validated['limit'], 1);
            $this->limit = self::intFromMixed($validated['limit'], 1);
            $this->page = 1;
            $this->skip = 0;
            $this->pagination = $this->limit;
        } else {
            $this->page = 1;
            $this->skip = 0;
            $this->take = $this->getDefaultPagination();
            $this->pagination = $this->take;
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    protected function conformFilterOperators(array &$filter): void
    {
        if (array_key_exists('operator', $filter)) {
            $operator = $filter['operator'];
            $filter['operator'] = (is_string($operator) || $operator instanceof RequestFilterOperator
                ? FilterOperator::tryFromRequestOperator($operator)
                : null) ?? FilterOperator::Equals;
        } elseif (array_key_exists('value', $filter)) {
            $filter['operator'] = FilterOperator::Equals;
        }
    }

    /**
     * @param  array{property:string,value:mixed,operator:FilterOperator}  $filter
     */
    protected function conformFilterValue(array &$filter): void
    {
        if ($filter['value'] === 'null' || $filter['value'] === null) {
            $filter['value'] = null;
        } elseif (in_array($filter['operator'], [FilterOperator::Like, FilterOperator::NotLike], true)) {
            $value = $filter['value'];

            if (is_string($value) && ! Str::startsWith($value, '%') && ! Str::endsWith($value, '%')) {
                $filter['value'] = '%' . $value . '%';
            }
        } elseif ($filter['operator'] === FilterOperator::In && is_string($filter['value'])) {
            $filter['value'] = is_json($filter['value']) ? json_decode($filter['value'], true) : explode(',', $filter['value']);
        }
    }

    /**
     * @param  array<mixed, mixed>  $raw
     */
    private function parseFilters(array $raw): FiltersGroup
    {
        $parsed = $this->conformFilterNode($raw, 0);

        if ($parsed instanceof FiltersGroup) {
            return $parsed;
        }

        if (is_array($parsed)) {
            return new FiltersGroup($parsed);
        }

        return new FiltersGroup([$parsed]);
    }

    /**
     * @param  array<mixed, mixed>  $raw
     * @return Filter|FiltersGroup|array<int, Filter|FiltersGroup>
     */
    private function conformFilterNode(array $raw, int $level): Filter|FiltersGroup|array
    {
        if (Arr::isList($raw)) {
            $result = [];

            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $conformed = $this->conformFilterNode($item, $level + 1);

                if ($conformed instanceof Filter || $conformed instanceof FiltersGroup) {
                    $result[] = $conformed;
                } elseif (is_array($conformed)) {
                    array_push($result, ...$conformed);
                }
            }

            if ($level === 0) {
                return new FiltersGroup($result);
            }

            return $result;
        }

        if (Arr::has($raw, 'filters')) {
            $nested = $raw['filters'];

            if (! is_array($nested)) {
                return new FiltersGroup([]);
            }

            $raw_operator = $raw['operator'] ?? null;
            $operator = is_string($raw_operator)
                ? (WhereClause::tryFrom(mb_strtolower($raw_operator)) ?? WhereClause::And)
                : WhereClause::And;
            $conformed = $this->conformFilterNode($nested, $level + 1);
            $filters_list = is_array($conformed) ? $conformed : [$conformed];

            return new FiltersGroup($filters_list, $operator);
        }

        $filter = $raw;
        $this->conformFilterOperators($filter);
        $property = $filter['property'] ?? null;
        $operator = $filter['operator'] ?? FilterOperator::Equals;

        if (! is_string($property) || ! $operator instanceof FilterOperator) {
            return new Filter('', null, FilterOperator::Equals);
        }

        $typed_filter = [
            'property' => $property,
            'value' => $filter['value'] ?? null,
            'operator' => $operator,
        ];
        $this->conformFilterValue($typed_filter);

        return new Filter($typed_filter['property'], $typed_filter['value'], $typed_filter['operator']);
    }

    /**
     * @param  array<int, string>  $relations
     * @return array<int, string>
     */
    private function conformRelations(array $relations): array
    {
        return array_map(function (string $relation): string {
            $result = preg_replace(
                [sprintf('/^%s\./', $this->mainEntity), sprintf('/^%s\./', $this->model->getTable())],
                '',
                $relation,
            );

            return $result ?? $relation;
        }, $relations);
    }

    private function getDefaultPagination(): int
    {
        $value = app(PerModelSettingResolver::class)->int('pagination', 25);

        return max(1, $value);
    }

    /**
     * @param  array<int, string|array{property:string,direction:SortDirection|string}>  $sorts
     * @return array<int, Sort>
     */
    private function conformSorts(array $sorts): array
    {
        $result = [];

        foreach ($sorts as $value) {
            if (is_string($value)) {
                $result[] = new Sort($value);
            } else {
                $result[] = new Sort($value['property'], $value['direction'] ?? SortDirection::Asc);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $groups
     */
    private function addGroupsToColumns(array $groups): void
    {
        if ($this->columns !== []) {
            $all_columns_name = array_map(fn (Column $column): string => $column->name, $this->columns);

            foreach ($groups as $group) {
                if (! in_array($group, $all_columns_name, true)) {
                    $this->columns[] = new Column($group);
                }
            }
        }
    }

    private static function intFromMixed(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return array<int, string|array{property:string,direction:SortDirection|string}>
     */
    private static function sortInput(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sorts = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $sorts[] = $item;

                continue;
            }

            if (! is_array($item) || ! isset($item['property']) || ! is_string($item['property'])) {
                continue;
            }

            $direction = $item['direction'] ?? SortDirection::Asc;

            if (! is_string($direction) && ! $direction instanceof SortDirection) {
                $direction = SortDirection::Asc;
            }

            $sorts[] = [
                'property' => $item['property'],
                'direction' => $direction,
            ];
        }

        return $sorts;
    }
}
