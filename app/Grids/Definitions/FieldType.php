<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

enum FieldType: string
{
    case Column = 'column';
    case Count = 'witCount';
    case Sum = 'withSum';
    case Avg = 'withAverage';
    case Min = 'withMin';
    case Max = 'withMax';
    case Method = 'method';
    case Append = 'append';
}
