<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

final class OptimisticLockRemoveCommand extends OptimisticLockAddCommand
{
    public $signature = 'lock:optimistic-remove {model} {--namespace=}';

    public $description = 'Add a migration to remove optimistic locking columns to a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    protected $operation = 'remove';
}
