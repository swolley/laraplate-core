<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * How a version row stores its change: only the changed columns, or the whole image.
 *
 * Absorbed from overtrue/laravel-versionable (MIT License, Copyright (c) 2019 overtrue).
 * The case names are kept as in the library so that existing call sites only change their import.
 */
enum VersionStrategy: string
{
    case DIFF = 'DIFF';
    case SNAPSHOT = 'SNAPSHOT';
}
