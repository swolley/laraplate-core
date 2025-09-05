<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Builder;

trait SortableTrait
{
    use \Spatie\EloquentSortable\SortableTrait {
        scopeOrdered as protected scopeOrderedTrait;
    }

    // public function determineOrderColumnName(): string
    // {
    //     return $this->determineOrderColumnNameTrait();

    //     // $column_name = $this->determineOrderColumnNameTrait();

    //     // if (! str_contains($column_name, $this->getTable() . '.')) {
    //     //     return $this->getTable() . '.' . $column_name;
    //     // }

    //     // return $column_name;
    // }

    public function scopeOrdered(Builder $query, string $direction = 'asc')
    {
        return $query->orderBy($this->qualifyColumn($this->determineOrderColumnName()), $direction);
    }
}
