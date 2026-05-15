<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

enum OrderType: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
