<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Modification;

/**
 * A request reached its disapproval quorum; nothing was applied.
 */
final readonly class ModificationRejected
{
    public function __construct(
        public Modification $modification,
        public ?Model $modifiable,
    ) {}
}
