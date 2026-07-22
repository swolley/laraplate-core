<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum VersionChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
