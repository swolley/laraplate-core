<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum VersionSetKind: string
{
    case Change = 'change';
    case Revert = 'revert';
}
