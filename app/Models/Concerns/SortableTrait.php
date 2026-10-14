<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait SortableTrait
{
    use \Spatie\EloquentSortable\SortableTrait {
        scopeOrdered as private scopeOrderedTrait;
        shouldSortWhenCreating as private shouldSortWhenCreatingByConfiguration;
    }

    private bool $keepsPositionWhenCreating = false;

    /**
     * Create this record at the position it was given instead of last, for a writer that owns the order,
     * such as an import mirroring its source.
     */
    public function keepPositionWhenCreating(): static
    {
        $this->keepsPositionWhenCreating = true;

        return $this;
    }

    public function shouldSortWhenCreating(): bool
    {
        return ! $this->keepsPositionWhenCreating && $this->shouldSortWhenCreatingByConfiguration();
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
    #[Scope]
    protected function ordered(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy($this->qualifyColumn($this->determineOrderColumnName()), $direction);
    }
}
