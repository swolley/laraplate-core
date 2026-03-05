<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Override;

final class LockedRemoveCommand extends LockedAddCommand
{
    #[Override]
    public $signature = 'lock:locked-remove {model} {--namespace=}';

    #[Override]
    public $description = 'Add a migration to remove locked columns to a model <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    #[Override]
    protected $operation = 'remove';
}
