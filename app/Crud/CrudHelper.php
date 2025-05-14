<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

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

final class CrudHelper
{
    /**
     * @throws InvalidArgumentException
     */
    public function prepareQuery(Builder $query, SelectRequestData $request_data): void
    {
        $main_model = $query->getModel();
        $main_entity = $main_model->getTable();
        $relations_sorts = [];
        $relations_columns = [];
        $relations_filters = [];

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
                // TODO: qui mancano ancora le colonne utili a fare le relation se la foreign key si trova sulla main table
                $this->addForeignKeysToSelectedColumns($query, $only_standard_columns, $main_model, $main_entity);
                $query->select($only_standard_columns);
            } elseif ($type === 'relations' && $cols === []) {
                foreach ($cols as $relation => $relation_cols) {
                    $this->sortColumns($query, $relation_cols);
                    $only_relation_columns = [];

                    foreach ($relation_cols as $column) {
                        if ($column->type === ColumnType::COLUMN) {
                            $only_relation_columns[] = $column;
                        }
                    }
                    $relations_columns[$relation] = $only_relation_columns;

                    if (! in_array($relation, $request_data->relations, true)) {
                        $request_data->relations[] = $relation;
                    }
                }
            }
        }

        if ($request_data instanceof ListRequestData) {
            // check for sorts and prepare data
            if (isset($request_data->sort)) {
                foreach ($request_data->sort as $column) {
                    if (preg_match("/^\w+\.\w+$/", (string) $column->property)) {
                        $query->orderBy($column->property, $column->direction->value);
                    } else {
                        $index = str_replace($main_entity . '.', '', $column->property);
                        $splitted = $this->splitColumnNameOnLastDot($index);
                        $cloned_column = new Sort($splitted[1], $column->direction);

                        if (! array_key_exists($index, $columns['relations'])) {
                            $relations_sorts[$splitted[0]] = [$cloned_column];
                        } else {
                            $relations_sorts[$splitted[0]][] = $cloned_column;
                        }
                    }
                }
            }
            // if (isset($request_data->group_by)) {
            //     $request_data->group_by = array_map(fn (string $group) => str_replace($main_entity . '.', '', $group), $request_data->group_by);
            // }
        }

        if ($request_data instanceof ListRequestData && isset($request_data->filters)) {
            // TODO: come faccio a smontare filters e raggrupparlo per la singola relation?
            // forse devo fare un filter ricorsivo nell'oggetto FiltersGroup e tirare fuori solo i campi relativi alla singoal relation o sottorelation conservando la struttura originale?

            // foreach ($request_data->filters->filters as $filter) {
            //     if (preg_match("/^\w+\.\w+$/", $filter->property)) {
            //         $index = str_replace($main_entity . '.', '', $filter->property);
            //         $splitted = self::splitColumnNameOnLastDot($index);
            //         $cloned_filter = new Filter($splitted[1], $filter->value, $filter->operator);
            //         $relation_name = preg_replace('/\.' . $splitted[1] . '$/', '', $filter->property);
            //         if (! array_key_exists($index, $relations_filters[$relation_name])) {
            //             $relations_filters[$relation_name] = [$cloned_filter];
            //         } else {
            //             $relations_filters[$relation_name][] = $cloned_filter;
            //         }
            //     }
            // }

            $this->recursivelyApplyFilters($query, $request_data->filters, $columns['relations']);
        }

        if ($request_data->relations !== []) {
            $this->applyRelations($query, $request_data->relations, $relations_columns, $relations_sorts, $columns['aggregates'], $relations_filters);
        }
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
            // used only for quick search instead of array_filter
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
     * removes unnecessary unnecessary relationships.
     *
     * @param  array<int,string>  $relations
     */
    private function cleanRelations(array &$relations): void
    {
        // tengo parent commentato per ricordarmi che non va aggiunto
        $black_list = [
            'history',
            'ancestors',
            'ancestorsAndSelf',
            'bloodline',
            'children',
            'childrenAndSelf',
            'descendants',
            'descendantsAndSelf',
            // , 'parent,'
            'parentAndSelf',
            'rootAncestor',
            'siblings',
            'siblingsAndSelf',
        ];
        $relations = array_filter($relations, fn ($relation): bool => ! in_array($relation, $black_list, true));
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
        array_shift($exploded);

        while ($exploded !== []) {
            $relation_model = $relation_model->{array_shift($exploded)}()->getModel();
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
        if (mb_substr_count($filter->property, '.') > 1) {
            // relations
            $splitted = $this->splitProperty($query->getModel(), $filter->property);
            $query->{$method . 'Has'}($splitted['relation'], function (Builder $q) use ($filter, &$method, $splitted, &$relation_columns): void {
                if ($splitted['field'] === 'deleted_at') {
                    $permission = "{$splitted['connection']}.{$splitted['table']}.delete";
                    $user = Auth::user();

                    if ($user && $user->can($permission)) {
                        $q->withTrashed();
                    }
                }
                $cloned_filter = new Filter($splitted['field'], $filter->value, $filter->operator);
                $this->applyFilter($q, $cloned_filter, $method, $relation_columns);
            });
        } elseif ($filter->value === null) {
            // is or is not null
            $method .= $filter->operator === FilterOperator::EQUALS ? 'Null' : 'NotNull';
            $query->{$method}($filter->property);
        } elseif (in_array($filter->operator, [FilterOperator::LIKE, FilterOperator::NOT_LIKE], true)) {
            // like not like
            $method .= Str::studly($filter->operator->value);
            $query->{$method}($filter->property, $filter->value);
        } else {
            // all the others
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
                $query->{$method}(fn (Builder $q) => $this->recursivelyApplyFilters($q, $subfilter, $relation_columns));
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
        $all_columns_name = array_map(fn (Column $column): string => $column->name, $columns);
        $primary_key = Arr::wrap($query->getModel()->getKeyName());

        foreach ($primary_key as $key) {
            if (! in_array($key, $all_columns_name, true)) {
                array_unshift($columns, new Column($key, ColumnType::COLUMN));
                $all_columns_name[] = $key;
            }
        }
    }

    /**
     * @param  array<string,array<int,Column>>  $relation_columns
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
        $query->select($simple_columns);
    }

    /**
     * apply only direct aggregate relations on the current related entity.
     *
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

                if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
                    $query->{$method}([$subrelation]);
                } else {
                    $query->{$method}([$subrelation . '.' . $col->name]);
                }
            }
            unset($relations_aggregates[$aggregate_relation]);
        }
    }

    /**
     * @param  array<int,string>  $selectColumns
     */
    private function addForeignKeysToSelectedColumns(Builder|Relation $query, array &$selectColumns, ?Model $model = null, ?string $table = null): void
    {
        if (! $model instanceof Model) {
            $model = $query->getModel();
        }
        $table ??= $model->getTable();

        foreach (Inspect::foreignKeys($table, $model->getConnection()->getName()) as $foreign) {
            foreach ($foreign->columns as $column) {
                $selectColumns[] = new Column($column);
            }
        }
    }

    /**
     * @param  array<string,array<int,string>>  $relations_columns
     * @param  array<string,array<int,Sort>>  $relations_sorts
     * @param  array<string,array<int,Column>>  $relations_aggregates
     * @param  array<string,array<int,Filter>>  $relations_filters
     */
    private function createRelationCallback(Relation $query, string $relation, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
    {
        if ($relations_columns[$relation] !== []) {
            $this->addForeignKeysToSelectedColumns($query, $relations_columns[$relation]);
            $this->applyColumnsToSelect($query, $relations_columns[$relation]);
        }

        $this->applyAggregatesToQuery($query, $relations_aggregates, $relation);

        if (isset($relations_filters[$relation])) {
            $this->recursivelyApplyFilters($query, $relations_filters[$relation], $relations_columns[$relation]);
        }

        if ($relations_sorts[$relation] !== []) {
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
     */
    private function applyRelations(Builder $query, array $relations, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
    {
        /** @var array<int,string> $merged_relations */
        $merged_relations = array_unique(array_merge($relations, array_keys($relations_sorts), array_keys($relations_columns)));
        $this->cleanRelations($relations);

        // apply only direct aggregate relations on the main entity
        foreach ($relations_aggregates as $relation => $aggregates_cols) {
            if (Str::contains($relation, '.')) {
                continue;
            }

            foreach ($aggregates_cols as $col) {
                $method = 'with' . ucfirst((string) $col->type->value);

                if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
                    $query->{$method}([$relation]);
                } else {
                    $query->{$method}([$relation . '.' . $col->name]);
                }
            }
            unset($relations_aggregates[$relation]);
        }

        /** @var array<string,callable(Relation):void> $withs */
        $withs = [];

        foreach ($merged_relations as $relation) {
            $withs[$relation] = function (Relation $q) use ($relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters): void {
                $this->createRelationCallback($q, $relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters);
            };
        }
        $query->with($withs);
    }
}
