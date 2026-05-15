<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum ColumnType: string
{
    // common fo all the queries
    case Column = 'column';
    case Count = 'count';
    case Sum = 'sum';
    case Avg = 'average';
    case Min = 'min';
    case Max = 'max';
    // only if is a mapped model and not DynanicEntity
    case Append = 'append';
    case Method = 'method';

    public function isAggregateColumn(): bool
    {
        return ! in_array($this->value, [ColumnType::Column->value, ColumnType::Append->value, ColumnType::Method->value], true);
    }
}
