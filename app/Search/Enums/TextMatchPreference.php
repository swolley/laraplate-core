<?php

declare(strict_types=1);

namespace Modules\Core\Search\Enums;

enum TextMatchPreference: string
{
    case Auto = 'auto';
    case Strict = 'strict';
    case Balanced = 'balanced';
    case Tolerant = 'tolerant';
}
