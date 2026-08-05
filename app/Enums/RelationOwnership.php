<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum RelationOwnership: string
{
    case Reference = 'reference';
    case Owned = 'owned';
}
