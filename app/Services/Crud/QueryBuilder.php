<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\SelectRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\Inspect;
use ReflectionMethod;

/**
 * QueryBuilder - prepares Eloquent queries from CRUD request data.
 *
 * This class is responsible ONLY for query manipulation:
 * - Applying columns, filters, sorts, relations
 * - Preparing the query builder based on SelectRequestData/ListRequestData
 *
 * Authorization logic (permissions and ACLs) is handled by AuthorizationService.
 * The ACL filters should be injected into the request BEFORE calling prepareQuery().
 *
 * Usage:
 * ```php
 * // In CrudService:
 * $auth->injectAclFilters($requestData, $permission_name);  // Inject ACL filters
 * $query_builder->prepareQuery($query, $requestData);        // Now filters include ACLs
 * ```
 */
final class QueryBuilder
{
    /**
     * Prepare the query based on request data.
     *
     * This applies columns, filters, sorts, and relations from the request.
     * ACL filters should already be injected into $request_data->filters.
     *
     * @throws InvalidArgumentException
     */
    public function prepareQuery(Builder $query, SelectRequestData $request_data): void
    {
        $main_model = $query->getModel();
        $main_entity = $main_model->getTable();
        $relations_sorts = [];
        $relations_columns = [];
        $relations_filters = [];
        $normalized_relations = $this->normalizeRelations($request_data->relations);
        $computed_columns = $this->extractComputedColumns($main_entity, $request_data->columns);
        $computed_main = $computed_columns['main'];
        $computed_relations = $computed_columns['relations'];
        $computed_main_dependencies = $this->resolveComputedDependencies($main_model, $computed_main);
        $force_select_all_main = $computed_main_dependencies['force_select_all'];

        if ($computed_main['append'] !== []) {
            $this->applyModelAppends($main_model, $computed_main['append']);
        }

        if ($computed_main_dependencies['relations'] !== []) {
            $normalized_relations = array_values(array_unique(array_merge($normalized_relations, $computed_main_dependencies['relations'])));
        }

        $columns = $this->groupColumns($main_entity, $request_data->columns);

        foreach ($columns as $type => $cols) {
            if ($type === 'main' && $cols !== []) {
                $this->sortColumns($query, $cols);

                /** @var array<int,string> $only_standard_columns */
                $only_standard_columns = [];

                foreach ($cols as $column) {
                    if ($column->type === ColumnType::COLUMN) {
                        $only_standard_columns[] = $column->name;
                    }
                }

                if ($computed_main_dependencies['columns'] !== [] && ! $force_select_all_main) {
                    foreach ($computed_main_dependencies['columns'] as $dependency_column) {
                        if (! in_array($dependency_column, $only_standard_columns, true)) {
                            $only_standard_columns[] = $dependency_column;
                        }
                    }
                }

                if ($force_select_all_main) {
                    $only_standard_columns = [$main_entity . '.*'];
                }

                if (! $force_select_all_main) {
                    $this->addForeignKeysToSelectedColumns($query, $only_standard_columns, $main_model, $main_entity);
                }
                $query->select($only_standard_columns);
            } elseif ($type === 'relations' && $cols !== []) {
                foreach ($cols as $relation => $relation_cols) {
                    $only_relation_columns = [];

                    foreach ($relation_cols as $column) {
                        if ($column->type === ColumnType::COLUMN) {
                            $only_relation_columns[] = $column;
                        }
                    }

                    $relations_columns[$relation] = $only_relation_columns;

                    if (! in_array($relation, $normalized_relations, true)) {
                        $normalized_relations[] = $relation;
                    }
                }
            }
        }

        if ($request_data instanceof ListRequestData) {
            if (isset($request_data->sort)) {
                foreach ($request_data->sort as $column) {
                    $property = (string) $column->property;

                    if (! Str::contains($property, '.')) {
                        $query->orderBy($property, $column->direction->value);

                        continue;
                    }

                    if (Str::startsWith($property, $main_entity . '.')) {
                        $query->orderBy($property, $column->direction->value);

                        continue;
                    }

                    $index = str_replace($main_entity . '.', '', $property);
                    $splitted = $this->splitColumnNameOnLastDot($index);

                    if (! isset($splitted[1])) {
                        continue;
                    }

                    $cloned_column = new Sort($splitted[1], $column->direction);
                    $relations_sorts[$splitted[0]][] = $cloned_column;
                }
            }
        }

        if ($request_data instanceof ListRequestData && isset($request_data->filters)) {
            $this->recursivelyApplyFilters($query, $request_data->filters, $columns['relations']);
        }

        if ($normalized_relations !== []) {
            $this->applyRelations($query, $normalized_relations, $relations_columns, $relations_sorts, $columns['aggregates'], $relations_filters, $computed_relations);
        }
    }

    /**
     * Apply a FiltersGroup directly to a query.
     *
     * Useful when you need to apply filters outside of the normal request flow.
     *
     * @param  array<string,array<int,Column>>  $relation_columns
     */
    public function applyFilters(Builder $query, FiltersGroup $filters, array &$relation_columns = []): void
    {
        $this->recursivelyApplyFilters($query, $filters, $relation_columns);
    }

    /**
     * @return non-empty-array<array-key, string>
     */
    private function splitColumnNameOnLastDot(string $name): array
    {
        return preg_split('/\.(?=[^.]*$)/', $name, 2);
    }

    /**
     * @param  array<int,Column>  $columns_filters
     * @return array{main:array<Column>,relations:array<string,array<Column>>,aggregates:array<string,array<Column>>}
     */
    private function groupColumns(string &$mainEntity, array $columns_filters): array
    {
        $columns = [
            'main' => [],
            'relations' => [],
            'aggregates' => [],
        ];

        if ($columns_filters !== []) {
            /** @var array<int,string> $all_relations_names */
            $all_relations_names = [];

            foreach ($columns_filters as $column) {
                $index = str_replace($mainEntity . '.', '', $column->name);

                if (preg_match("/^\w+\.\w+$/", $column->name) && $column->type === ColumnType::COLUMN) {
                    $columns['main'][] = new Column($index, $column->type);
                } else {
                    $splitted = $this->splitColumnNameOnLastDot($index);

                    if (! isset($splitted[1])) {
                        $splitted[1] = '*';
                    }

                    if ($column->type === ColumnType::COLUMN) {
                        $remapped_column = new Column($splitted[1], $column->type);

                        if (! in_array($splitted[0], $all_relations_names, true)) {
                            $columns['relations'][$splitted[0]] = [$remapped_column];
                            $all_relations_names[] = $splitted[0];
                        } else {
                            $columns['relations'][$splitted[0]][] = $remapped_column;
                        }
                    } elseif ($column->type->isAggregateColumn()) {
                        $cloned_column = new Column($splitted[1], $column->type);

                        if (! array_key_exists($index, $columns['aggregates'])) {
                            $columns['aggregates'][$splitted[0]] = [$cloned_column];
                        } else {
                            $columns['aggregates'][$splitted[0]][] = $cloned_column;
                        }
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array<int,string>  $relations
     */
    private function cleanRelations(array &$relations): void
    {
        $black_list = [
            'history',
            'ancestors',
            'ancestorsAndSelf',
            'bloodline',
            'children',
            'childrenAndSelf',
            'descendants',
            'descendantsAndSelf',
            'parentAndSelf',
            'rootAncestor',
            'siblings',
            'siblingsAndSelf',
        ];
        $relations = array_filter($relations, fn (string $relation): bool => ! in_array($relation, $black_list, true));
    }

    /**
     * @return array{relation:string,connection:'default'|mixed,table:mixed,field:string|null}
     */
    private function splitProperty(Builder|Model $model, string $property): array
    {
        /** @var array<int,string> $exploded */
        $exploded = explode('.', $property);

        if ($exploded !== [] && $exploded[0] === $model->getTable()) {
            array_shift($exploded);
        }

        $field = array_pop($exploded);
        $relation = implode('.', $exploded);
        $relation_model = $model instanceof Model ? $model : $model->getModel();

        foreach ($exploded as $relation_name) {
            $relation_model = $relation_model->{$relation_name}()->getModel();
        }

        return [
            'relation' => $relation,
            'connection' => $relation_model->connection ?? 'default',
            'table' => $relation_model->getTable(),
            'field' => $field,
        ];
    }

    /**
     * @param  array<string,array<int,Column>>  $relation_columns
     */
    private function applyFilter(Builder $query, Filter $filter, string &$method, array &$relation_columns): void
    {
        $path_length = mb_substr_count($filter->property, '.');

        if ($path_length >= 1) {
            $splitted = $this->splitProperty($query->getModel(), $filter->property);
            $query_model = $query->getModel();

            if (method_exists($query_model, $splitted['field'])) {
                $reflected_method = new ReflectionMethod($query_model, $splitted['field']);

                if (is_a($reflected_method->getReturnType()->__toString(), Relation::class, true)) {
                    $returned_relation_entity = $query_model->{$splitted['field']}()->getModel();
                    $splitted['relation'] = $splitted['field'];
                    $splitted['table'] = $returned_relation_entity->getTable();
                    $splitted['field'] = $returned_relation_entity->getKeyName();
                    $splitted['connection'] = $returned_relation_entity->getConnection()->getName();

                    $query->has(
                        $splitted['relation'],
                        $filter->operator->value,
                        $filter->value,
                        Str::startsWith($method, 'or') ? 'or' : 'and',
                        static fn (Builder $q) => $q->withoutGlobalScope('global_ordered'),
                    );

                    return;
                }
            }

            if ($splitted['relation'] !== '') {
                $has_method = $method . 'Has';

                if (method_exists($query, $has_method)) {
                    $query->{$has_method}($splitted['relation'], function (Builder $q) use ($filter, &$method, $splitted, &$relation_columns): void {
                        $q->withoutGlobalScope('global_ordered');

                        if ($splitted['field'] === 'deleted_at') {
                            $permission = sprintf('%s.%s.delete', $splitted['connection'], $splitted['table']);
                            $user = Auth::user();

                            if ($user && $user->can($permission)) {
                                $q->withTrashed();
                            }
                        }

                        $cloned_filter = new Filter($splitted['field'], $filter->value, $filter->operator);
                        $this->applyFilter($q, $cloned_filter, $method, $relation_columns);
                    });
                }

                return;
            }
        }

        if ($filter->value === null) {
            $method .= $filter->operator === FilterOperator::EQUALS ? 'Null' : 'NotNull';
            $query->{$method}($filter->property);

            return;
        }

        if ($filter->operator === FilterOperator::IN) {
            $in_method = $method === 'orWhere' ? 'orWhereIn' : 'whereIn';
            $query->{$in_method}($filter->property, Arr::wrap($filter->value));

            return;
        }

        if ($filter->operator === FilterOperator::BETWEEN && is_array($filter->value)) {
            $between_method = $method === 'orWhere' ? 'orWhereBetween' : 'whereBetween';
            $query->{$between_method}($filter->property, $filter->value);

            return;
        }

        if (in_array($filter->operator, [FilterOperator::LIKE, FilterOperator::NOT_LIKE], true)) {
            $method .= Str::studly($filter->operator->value);
            $query->{$method}($filter->property, $filter->value);

            return;
        }

        if ($method !== '' && method_exists($query, $method)) {
            $query->{$method}($filter->property, $filter->operator->value, $filter->value);
        }
    }

    /**
     * @param  array<string,array<int,Column>>  $relation_columns
     */
    private function recursivelyApplyFilters(Builder|Relation $query, FiltersGroup|array $filters, array &$relation_columns): void
    {
        $iterable = is_array($filters) && Arr::isList($filters) ? $filters : $filters->filters;
        $method = $filters->operator === WhereClause::AND ? 'where' : 'orWhere';

        foreach ($iterable as &$subfilter) {
            if (isset($subfilter->filters)) {
                if ($method !== '' && method_exists($query, $method)) {
                    $query->{$method}(fn (Builder $q) => $this->recursivelyApplyFilters($q, $subfilter, $relation_columns));
                }
            } else {
                $this->applyFilter($query, $subfilter, $method, $relation_columns);
            }
        }
    }

    /**
     * @param  array<int,Column>  $columns
     */
    private function sortColumns(Builder|Relation $query, array &$columns): void
    {
        usort($columns, fn (Column $a, Column $b): int => $a->name <=> $b->name);

        $all_columns_name = [];

        foreach ($columns as $column) {
            $all_columns_name[] = $column->name;
        }

        $primary_key = Arr::wrap($query->getModel()->getKeyName());

        foreach ($primary_key as $key) {
            if (! in_array($key, $all_columns_name, true)) {
                array_unshift($columns, new Column($key, ColumnType::COLUMN));
                $all_columns_name[] = $key;
            }
        }
    }

    /**
     * @param  array<int,Column>  $relation_columns
     */
    private function applyColumnsToSelect(Builder|Relation $query, array &$relation_columns): void
    {
        $this->sortColumns($query, $relation_columns);
        $simple_columns = [];

        foreach ($relation_columns as $column) {
            if ($column->type === ColumnType::COLUMN) {
                $simple_columns[] = $column->name;
            }
        }

        if ($simple_columns === []) {
            return;
        }

        $query->select($simple_columns);
    }

    /**
     * @param  array<string,array<int,Column>>  $relations_aggregates
     */
    private function applyAggregatesToQuery(Builder|Relation $query, array &$relations_aggregates, string $relation): void
    {
        foreach ($relations_aggregates as $aggregate_relation => $aggregates_cols) {
            $escaped = preg_quote($relation);

            if (preg_match('/^' . $escaped . '\.\w+$/', $aggregate_relation) !== 1) {
                continue;
            }

            $subrelation = preg_replace('/^' . $escaped . '\./', '', $aggregate_relation);

            foreach ($aggregates_cols as $col) {
                $method = 'with' . ucfirst((string) $col->type->value);

                if ($col->type === ColumnType::COUNT) {
                    $query->{$method}([$subrelation]);

                    continue;
                }

                $query->{$method}($subrelation, $col->name);
            }

            unset($relations_aggregates[$aggregate_relation]);
        }
    }

    /**
     * @param  array<int,string>  $selectColumns
     */
    private function addForeignKeysToSelectedColumns(Builder|Relation $query, array &$selectColumns, ?Model $model = null, ?string $table = null, bool $as_columns = false): void
    {
        if (! $model instanceof Model) {
            $model = $query->getModel();
        }

        $table ??= $model->getTable();

        $existing_columns = [];

        foreach ($selectColumns as $select_column) {
            $existing_columns[] = $select_column instanceof Column ? $select_column->name : $select_column;
        }

        foreach (Inspect::foreignKeys($table, $model->getConnection()->getName()) as $foreign) {
            foreach ($foreign->columns as $column) {
                if (in_array($column, $existing_columns, true)) {
                    continue;
                }

                $selectColumns[] = $as_columns ? new Column($column) : $column;
                $existing_columns[] = $column;
            }
        }
    }

    /**
     * @param  array<string,array<int,Column>>  $relations_columns
     * @param  array<string,array<int,Sort>>  $relations_sorts
     * @param  array<string,array<int,Column>>  $relations_aggregates
     * @param  array<string,array<int,Filter>>  $relations_filters
     * @param  array<string,array{append:array<int,string>,method:array<int,string>}>  $computed_relations
     */
    private function createRelationCallback(Relation $query, string $relation, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters, array $computed_relations): void
    {
        $computed = $computed_relations[$relation] ?? ['append' => [], 'method' => []];
        $computed_dependencies = $this->resolveComputedDependencies($query->getModel(), $computed);
        $force_select_all = $computed_dependencies['force_select_all'];

        if ($computed['append'] !== []) {
            $this->applyModelAppends($query->getModel(), $computed['append']);
        }

        if ($computed_dependencies['relations'] !== []) {
            $query->with($computed_dependencies['relations']);
        }

        if (! $force_select_all && $computed_dependencies['columns'] !== []) {
            $this->mergeComputedDependencies($relations_columns, $relation, $computed_dependencies['columns']);
        } elseif ($force_select_all) {
            unset($relations_columns[$relation]);
        }

        if (array_key_exists($relation, $relations_columns) && $relations_columns[$relation] !== []) {
            $this->addForeignKeysToSelectedColumns($query, $relations_columns[$relation], as_columns: true);
            $this->applyColumnsToSelect($query, $relations_columns[$relation]);
        }

        $this->applyAggregatesToQuery($query, $relations_aggregates, $relation);

        if (isset($relations_filters[$relation])) {
            $this->recursivelyApplyFilters($query, $relations_filters[$relation], $relations_columns[$relation]);
        }

        if (array_key_exists($relation, $relations_sorts) && $relations_sorts[$relation] !== []) {
            foreach ($relations_sorts[$relation] as $sort) {
                $query->orderBy($sort->property, $sort->direction->value);
            }
        }
    }

    /**
     * @param  array<int,string>  $relations
     * @param  array<string,array<int,Column>>  $relations_columns
     * @param  array<string,array<int,Sort>>  $relations_sorts
     * @param  array<string,array<int,Column>>  $relations_aggregates
     * @param  array<string,array<int,Filter>>  $relations_filters
     * @param  array<string,array{append:array<int,string>,method:array<int,string>}>  $computed_relations
     */
    private function applyRelations(Builder $query, array $relations, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters, array $computed_relations): void
    {
        $relations = $this->normalizeRelations($relations);
        $merged_relations = array_unique(array_merge($relations, array_keys($relations_sorts), array_keys($relations_columns)));
        $this->cleanRelations($relations);

        foreach ($relations_aggregates as $relation => $aggregates_cols) {
            if (Str::contains($relation, '.')) {
                continue;
            }

            foreach ($aggregates_cols as $col) {
                $method = 'with' . ucfirst((string) $col->type->value);

                if ($col->type === ColumnType::COUNT) {
                    $query->{$method}([$relation]);

                    continue;
                }

                $query->{$method}($relation, $col->name);
            }

            unset($relations_aggregates[$relation]);
        }

        /** @var array<string,callable(Relation):void> $withs */
        $withs = [];

        foreach ($merged_relations as $relation) {
            $withs[$relation] = function (Relation $q) use ($relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters, $computed_relations): void {
                $this->createRelationCallback($q, $relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters, $computed_relations);
            };
        }

        $query->with($withs);
    }

    /**
     * @param  array<int,string|array{name:string}>  $relations
     * @return array<int,string>
     */
    private function normalizeRelations(array $relations): array
    {
        $normalized = [];

        foreach ($relations as $relation) {
            if (is_string($relation)) {
                $normalized[] = $relation;

                continue;
            }

            if (is_array($relation) && isset($relation['name'])) {
                $normalized[] = $relation['name'];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int,Column>  $columns
     * @return array{main:array{append:array<int,string>,method:array<int,string>},relations:array<string,array{append:array<int,string>,method:array<int,string>}>}
     */
    private function extractComputedColumns(string $main_entity, array $columns): array
    {
        $computed = [
            'main' => ['append' => [], 'method' => []],
            'relations' => [],
        ];

        foreach ($columns as $column) {
            if (! in_array($column->type, [ColumnType::APPEND, ColumnType::METHOD], true)) {
                continue;
            }

            $index = str_replace($main_entity . '.', '', $column->name);
            $splitted = $this->splitColumnNameOnLastDot($index);
            $relation = $splitted[1] ?? null ? $splitted[0] : '';
            $name = $splitted[1] ?? $splitted[0];
            $bucket = $column->type === ColumnType::APPEND ? 'append' : 'method';

            if ($relation === '') {
                $computed['main'][$bucket][] = $name;
            } else {
                if (! isset($computed['relations'][$relation])) {
                    $computed['relations'][$relation] = ['append' => [], 'method' => []];
                }

                $computed['relations'][$relation][$bucket][] = $name;
            }
        }

        return $computed;
    }

    /**
     * @param  array{append:array<int,string>,method:array<int,string>}  $computed
     * @return array{columns:array<int,string>,relations:array<int,string>,force_select_all:bool}
     */
    private function resolveComputedDependencies(Model $model, array $computed): array
    {
        $computed_names = array_values(array_unique(array_merge($computed['append'], $computed['method'])));
        $resolved = [
            'columns' => [],
            'relations' => [],
            'force_select_all' => false,
        ];

        if ($computed_names === []) {
            return $resolved;
        }

        if (! method_exists($model, 'crudComputedDependencies')) {
            $resolved['force_select_all'] = true;

            return $resolved;
        }

        /** @var array<string,mixed> $dependencies_map */
        $dependencies_map = $model->crudComputedDependencies();
        $table = $model->getTable();

        foreach ($computed_names as $computed_name) {
            $dependency = $dependencies_map[$computed_name] ?? $dependencies_map[$table . '.' . $computed_name] ?? null;

            if ($dependency === null) {
                $resolved['force_select_all'] = true;

                continue;
            }

            $dependency_columns = Arr::wrap($dependency['columns'] ?? $dependency);
            $dependency_relations = Arr::wrap($dependency['relations'] ?? []);

            foreach ($dependency_columns as $dependency_column) {
                $dependency_column = str_replace($table . '.', '', (string) $dependency_column);

                if (! in_array($dependency_column, $resolved['columns'], true)) {
                    $resolved['columns'][] = $dependency_column;
                }
            }

            foreach ($dependency_relations as $dependency_relation) {
                $dependency_relation = str_replace($table . '.', '', (string) $dependency_relation);

                if (! in_array($dependency_relation, $resolved['relations'], true)) {
                    $resolved['relations'][] = $dependency_relation;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string,array<int,Column>>  $relations_columns
     * @param  array<int,string>  $dependency_columns
     */
    private function mergeComputedDependencies(array &$relations_columns, string $relation, array $dependency_columns): void
    {
        if (! array_key_exists($relation, $relations_columns)) {
            $relations_columns[$relation] = [];
        }

        $existing_columns = array_map(static fn (Column $column): string => $column->name, $relations_columns[$relation]);

        foreach ($dependency_columns as $dependency_column) {
            if (in_array($dependency_column, $existing_columns, true)) {
                continue;
            }

            $relations_columns[$relation][] = new Column($dependency_column, ColumnType::COLUMN);
            $existing_columns[] = $dependency_column;
        }
    }

    /**
     * @param  array<int,string>  $appends
     */
    private function applyModelAppends(Model $model, array $appends): void
    {
        if ($appends === []) {
            return;
        }

        $model->append($appends);
    }
}
