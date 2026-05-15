<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum WhereClause: string
{
    case And = 'and';
    case Or = 'or';
}
