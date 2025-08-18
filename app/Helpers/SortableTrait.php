<?php

namespace Modules\Core\Helpers;

trait SortableTrait
{
    use \Spatie\EloquentSortable\SortableTrait {
        determineOrderColumnName as protected determineOrderColumnNameTrait;
    }

    public function determineOrderColumnName(): string
    {
        $column_name = $this->determineOrderColumnNameTrait();

        if (! str_contains($column_name, $this->getTable() . '.')) {
            return $this->getTable() . '.' . $column_name;
        }

        return $column_name;
    }
}
