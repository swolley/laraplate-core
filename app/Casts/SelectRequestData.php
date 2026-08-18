<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Support\Str;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Http\Requests\SelectRequest;

class SelectRequestData extends CrudRequestData
{
    /**
     * @var array<int, Column>
     */
    public protected(set) array $columns;

    /**
     * @var array<int, string>
     */
    public protected(set) array $relations;

    /**
     * @param  string|array<string>  $primaryKey
     * @param  array<string, mixed>  $validated
     */
    public function __construct(SelectRequest|GridRequest $request, string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey, $module);
        $this->columns = $this->conformColumns($validated['columns'] ?? []);
        $this->relations = $this->conformRelations($validated['relations'] ?? []);
    }

    /**
     * Namespace a column to the main model's real table, not the request's entity
     * alias. {@see QueryBuilder::groupColumns()} strips the table prefix, so both
     * ends must agree — otherwise, for entities whose route alias differs from their
     * table (prefixed module tables such as `cms_locations` behind the `locations`
     * alias), the prefix is never stripped and an aggregate/column is mis-keyed to
     * the alias (e.g. a `contents` count becomes `withCount(['locations'])`).
     *
     * A column already namespaced to the table is kept; one namespaced to the route
     * alias is re-namespaced to the table; a bare column is namespaced to the table.
     */
    private function conformColumnName(string $column): string
    {
        $table = $this->model->getTable();

        if ($column === $table || Str::startsWith($column, $table . '.')) {
            return $column;
        }

        if ($this->mainEntity !== $table && Str::startsWith($column, $this->mainEntity . '.')) {
            return $table . '.' . Str::after($column, $this->mainEntity . '.');
        }

        return $table . '.' . $column;
    }

    /**
     * @param  array<int,string|array{name:string,type:string}>  $columns
     * @return array<int,Column>
     */
    private function conformColumns(array $columns): array
    {
        foreach ($columns as &$column) {
            if (is_string($column)) {
                $column = new Column($this->conformColumnName($column));
            } else {
                $column = new Column($this->conformColumnName($column['name']), $column['type']);
            }
        }

        return $columns;
    }

    /**
     * @param  array<int,string>  $relations
     * @return array<int,string>
     */
    private function conformRelations(array $relations): array
    {
        foreach ($relations as &$relation) {
            if (Str::startsWith($relation, $this->mainEntity)) {
                $relation = str_replace($this->mainEntity . '.', '', $relation);
            }
        }

        return $relations;
    }
}
