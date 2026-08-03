<?php

declare(strict_types=1);

namespace Modules\Core\Import\Enums;

enum ExternalRecordState
{
    case Missing;
    case Unchanged;
    case Changed;
}
