<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Modification;

final class ModificationApproved
{
    public function __construct(
        public readonly Modification $modification,
        public readonly Model $modifiable,
    ) {}
}
