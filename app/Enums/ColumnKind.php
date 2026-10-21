<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * What an aggregate may do with a column: `Numeric` is summed, averaged, minimised and maximised, `Temporal`
 * is minimised, maximised and cut into buckets, `Other` is only counted and grouped by.
 */
enum ColumnKind: string
{
    case Numeric = 'numeric';
    case Temporal = 'temporal';
    case Other = 'other';
}
