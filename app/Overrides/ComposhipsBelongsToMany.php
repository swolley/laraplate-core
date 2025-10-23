<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as BaseCollection;
use Override;

/**
 * @property string|array<string> $parentKey
 */
final class ComposhipsBelongsToMany extends BelongsToMany
{
    #[Override]
    public function getQualifiedForeignPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->foreignPivotKey);
    }

    #[Override]
    public function getQualifiedRelatedPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->relatedPivotKey);
    }

    #[Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
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
            $compare_key,
        );
    }

    #[Override]
    public function getExistenceCompareKey(): string|array
    {
        if (is_array($this->foreignPivotKey)) {
            return array_map($this->getQualifiedForeignPivotKeyName(...), $this->foreignPivotKey);
        }

        return $this->getQualifiedForeignPivotKeyName();
    }

    #[Override]
    public function addConstraints(): void
    {
        $this->performJoin();

        if (self::$constraints) {
            $this->addWhereConstraints();
        }
    }

    #[Override]
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

    #[Override]
    public function qualifyPivotColumn($column): array|string|Expression
    {
        if (is_array($column)) {
            return array_map(parent::qualifyPivotColumn(...), $column);
        }

        return parent::qualifyPivotColumn($column);
    }

    #[Override]
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        $query->join($this->table, function (JoinClause $join): void {
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

        $query->join($this->parent->getTable(), function (JoinClause $join): void {
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

    /**
     * @param  array<int,Model>  $models
     * @param  string|array<int,string>  $key
     * @return array<int,string>
     */
    #[Override]
    protected function getKeys(array $models, $key = null): array
    {
        $keys = [];

        foreach ($models as $model) {
            $keys[] = is_array($key) ? array_map(fn (string $k) => $model->{$k}, $key) : $model->{$key};
        }

        $keys = array_unique($keys, SORT_REGULAR);
        sort($keys);

        return $keys;
    }

    #[Override]
    protected function addWhereConstraints(): self
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

    #[Override]
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
            ->map(fn ($column): string => $this->qualifyPivotColumn($column) . ' as pivot_' . $column)
            ->unique()
            ->all();
    }
}
