<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Models\Modification;

final class ModificationPreProcessingCompleted
{
    public function __construct(
        public readonly Modification $modification,
        public readonly string $processing_type,
    ) {}
}
