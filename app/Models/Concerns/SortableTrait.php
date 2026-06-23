<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait SortableTrait
{
    use \Spatie\EloquentSortable\SortableTrait {
        scopeOrdered as private scopeOrderedTrait;
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy($this->qualifyColumn($this->determineOrderColumnName()), $direction);
    }
}
