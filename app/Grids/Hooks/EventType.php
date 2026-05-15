<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

enum EventType: string
{
    case PreSelect = 'retrieving';
    case PostSelect = 'retrieved';
    case PreInsert = 'creating';
    case PostInsert = 'created';
    case PreUpdate = 'updating';
    case PostUpdate = 'updated';
    case PreDelete = 'deleting';
    case PostDelete = 'deleted';
}
