<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum SearchMode: string
{
    case Auto = 'auto';
    case Basic = 'basic';
    case Orchestrated = 'orchestrated';
}
