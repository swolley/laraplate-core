<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Models\Setting;

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

    public protected(set) array $sort;

    public protected(set) ?FiltersGroup $filters;

    public array $group_by = [];

    /**
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(ListRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);

        $this->extractPagination($validated);

        if (! isset($this->limit)) {
            $this->limit = (int) ($validated['limit'] ?? $this->pagination);
        }

        $this->count = isset($validated['count']) ? (int) $validated['count'] : false;
        $this->sort = $this->conformSorts($validated['sort'] ?? []);
        $this->relations = $this->conformRelations($validated['relations'] ?? []);

        if (isset($validated['filters'])) {
            $this->conformFiltersToQueryBuilderFormat($validated['filters']);
            $this->filters = $validated['filters'];
        } else {
            $this->filters = null;
        }

        if (isset($validated['group_by'])) {
            $this->addGroupsToColumns($validated['group_by']);
            $validated['group_by'] = array_map(fn (string $group): ?string => preg_replace("/^{$mainEntity}\./", '', $group), $validated['group_by']);
            $this->group_by = $validated['group_by'];
        }
    }

    public function calculateTotalPages(int $totalRecords): int
    {
        return (int) ceil($totalRecords / $this->pagination);
    }

    protected function extractPagination(array $validated): void
    {
        if (isset($validated['pagination']) || isset($validated['page'])) {
            $this->take = $this->pagination = (int) ($validated['pagination'] ?? $this->getDefaultPagination());
            $this->page = (int) ($validated['page'] ?? 1);
            $this->skip = ($this->page - 1) * $this->pagination;
            $this->from = $this->skip + 1;
            $this->to = $this->from + $this->pagination;
        } elseif (isset($validated['from']) || isset($validated['to'])) {
            $this->from = (int) ($validated['from'] ?? 1);
            $this->skip = $this->from - 1;
            $this->to = isset($validated['to']) ? (int) $validated['to'] : null;

            if ($this->to !== null && $this->to !== 0) {
                $this->take = $this->pagination = $this->to - $this->from;
            }
        } elseif (isset($validated['limit'])) {
            $this->take = $this->limit = (int) $validated['limit'];
            $this->page = 1;
            $this->skip = 0;
            $this->pagination = $this->limit;
        } else {
            $this->page = 1;
            $this->skip = 0;
            $this->take = $this->pagination = $this->getDefaultPagination();
        }
    }

    protected function conformRelations(array $relations): array
    {
        return array_map(fn (string $relation): string => preg_replace(["/^{$this->mainEntity}\./", "/^{$this->model->getTable()}\./"], '', $relation), $relations);
    }

    protected function conformFilterOperators(array &$filter): void
    {
        if (array_key_exists('operator', $filter)) {
            $filter['operator'] = FilterOperator::tryFromRequestOperator($filter['operator']);
        } elseif (array_key_exists('value', $filter)) {
            $filter['operator'] = FilterOperator::EQUALS;
        }
    }

    /**
     * @param  array{property:string,value:mixed,operator:FilterOperator}  $filter
     */
    protected function conformFilterValue(array &$filter): void
    {
        if ($filter['value'] === 'null' || $filter['value'] === null) {
            $filter['value'] = null;
        } elseif (in_array($filter['operator'], [FilterOperator::LIKE, FilterOperator::NOT_LIKE], true)) {
            $filter['value'] = ! Str::startsWith($filter['value'], '%') && ! Str::endsWith($filter['value'], '%') ? '%' . $filter['value'] . '%' : $filter['value'];
        } elseif ($filter['operator'] === FilterOperator::IN && is_string($filter['value'])) {
            $filter['value'] = is_json($filter['value']) ? json_decode($filter['value'], true) : explode(',', $filter['value']);
        }
    }

    protected function conformFiltersToQueryBuilderFormat(array|FiltersGroup|Filter &$filters, int $level = 0): void
    {
        if (Arr::isList($filters)) {
            foreach ($filters as &$filter) {
                $this->conformFiltersToQueryBuilderFormat($filter, $level + 1);
            }
            unset($filter);
        } elseif (Arr::has($filters, 'filters')) {
            $filters['operator'] = isset($filters['operator']) ? WhereClause::tryFrom(mb_strtolower((string) $filters['operator'])) : WhereClause::AND;
            $this->conformFiltersToQueryBuilderFormat($filters['filters'], $level + 1);
            $filters = new FiltersGroup($filters['filters'], $filters['operator']);
        } else {
            $this->conformFilterOperators($filters);
            $this->conformFilterValue($filters);
            $filters = new Filter($filters['property'], $filters['value'], $filters['operator']);
        }

        if ($level === 0 && ! ($filters instanceof FiltersGroup) && Arr::isList($filters)) {
            $filters = new FiltersGroup($filters);
        }
    }

    private function getDefaultPagination(): int
    {
        return (int) Setting::query()->where('name', 'pagination')->first('value')?->value ?? 25;
    }

    /**
     * @param  array<int, string|array{property:string,direction:SortDirection}>  $sorts
     * @return array<int, Sort>
     */
    private function conformSorts(array $sorts): array
    {
        foreach ($sorts as &$value) {
            if (is_string($value)) {
                $value = new Sort($value);
            } else {
                $value = new Sort($value['property'], $value['direction'] ?? SortDirection::ASC);
            }
        }
        unset($value);

        return $sorts;
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
}
