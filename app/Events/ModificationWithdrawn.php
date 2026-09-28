<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Modification;

/**
 * The author withdrew a request before its decision. The modification and its votes are
 * already deleted; `modifiable` is null for a create, which has no record yet.
 */
final readonly class ModificationWithdrawn
{
    public function __construct(
        public Modification $modification,
        public ?Model $modifiable,
    ) {}
}
