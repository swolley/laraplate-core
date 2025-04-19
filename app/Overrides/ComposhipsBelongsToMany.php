<?php

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComposhipsBelongsToMany extends BelongsToMany
{
    #[\Override]
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        $query->join($this->table, function (JoinClause $join) {
            // Join pivot -> parent
            if (is_array($this->relatedPivotKey)) {
                foreach ($this->relatedPivotKey as $index => $relatedPivotKey) {
                    $relatedKey = is_array($this->relatedKey) ? $this->relatedKey[$index] : $this->relatedKey;
                    $join->on($this->getQualifiedRelatedPivotKeyName($relatedPivotKey), '=', $this->related->qualifyColumn($relatedKey));
                }
            } else {
                $join->on($this->getQualifiedRelatedPivotKeyName(), '=', $this->related->qualifyColumn($this->relatedKey));
            }
        });

        $query->join($this->parent->getTable(), function (JoinClause $join) {
            // Join pivot -> related
            if (is_array($this->foreignPivotKey)) {
                foreach ($this->foreignPivotKey as $index => $foreignPivotKey) {
                    $parentKey = is_array($this->parentKey) ? $this->parentKey[$index] : $this->parentKey;
                    $join->on($this->getQualifiedForeignPivotKeyName($foreignPivotKey), '=', $this->parent->qualifyColumn($parentKey));
                }
            } else {
                $join->on($this->getQualifiedForeignPivotKeyName($this->foreignPivotKey), '=', $this->parent->qualifyColumn($this->parentKey));
            }
        });

        return $this;
    }

    #[\Override]
    public function getQualifiedForeignPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->foreignPivotKey);
    }

    #[\Override]
    public function getQualifiedRelatedPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->relatedPivotKey);
    }

    #[\Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        // return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        $parent_key = $this->getQualifiedParentKeyName();
        $compare_key = $this->getExistenceCompareKey();
        if (is_array($parent_key)) {
            foreach ($parent_key as $index => $key) {
                $query->whereColumn($key, '=', $compare_key[$index]);
            }
            return $query;
        }

        return $query->select($columns)->whereColumn(
            $parent_key,
            '=',
            $compare_key
        );
    }

    #[\Override]
    public function getExistenceCompareKey()
    {
        if (is_array($this->foreignPivotKey)) {
            return array_map(fn($key) => $this->getQualifiedForeignPivotKeyName($key), $this->foreignPivotKey);
        }

        return $this->getQualifiedForeignPivotKeyName();
    }

    /**
     * 
     * @param Model[] $models 
     * @param string|array $key 
     * @return array 
     */
    #[\Override]
    protected function getKeys(array $models, $key = null): array
    {
        $keys = [];
        foreach ($models as $model) {
            $keys[] = is_array($key) ? array_map(fn($k) => $model->{$k}, $key) : $model->{$key};
        }
        $keys = array_unique($keys, SORT_REGULAR);
        sort($keys);
        return $keys;
    }

    #[\Override]
    public function addConstraints()
    {
        $this->performJoin();

        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }

    #[\Override]
    protected function addWhereConstraints()
    {
        if (is_array($this->parentKey)) {
            foreach ($this->parentKey as $parentKey) {
                $this->query->where($this->parent->qualifyColumn($parentKey), '=', $this->parent->{$parentKey});
            }
            return $this;
        }

        parent::addWhereConstraints();
        return $this;
    }

    #[\Override]
    public function getResults()
    {
        if (is_string($this->parentKey)) {
            return parent::getResults();
        }

        $is_null = 0;
        foreach ($this->parentKey as $parentKey) {
            if (is_null($this->parent->{$parentKey})) {
                $is_null++;
            }
        }

        return $is_null === count($this->parentKey)
            ? $this->related->newCollection()
            : $this->get();
    }

    #[\Override]
    public function qualifyPivotColumn($column)
    {
        if (is_array($column)) {
            return array_map(fn($c) => parent::qualifyPivotColumn($c), $column);
        }

        return parent::qualifyPivotColumn($column);
    }

    #[\Override]
    protected function aliasedPivotColumns()
    {
        $collection = new BaseCollection([]);
        if (is_array($this->foreignPivotKey)) {
            $collection = $collection->merge($this->foreignPivotKey);
        } else {
            $collection->push($this->foreignPivotKey);
        }
        if (is_array($this->relatedPivotKey)) {
            $collection = $collection->merge($this->relatedPivotKey);
        } else {
            $collection->push($this->relatedPivotKey);
        }
        $collection = $collection->merge($this->pivotColumns);

        return $collection
            ->map(fn($column) => $this->qualifyPivotColumn($column) . ' as pivot_' . $column)
            ->unique()
            ->all();
    }
}
