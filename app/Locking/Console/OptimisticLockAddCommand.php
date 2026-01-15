<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Illuminate\Database\Eloquent\Model;
use Override;

class OptimisticLockAddCommand extends LockedAddCommand
{
    public $signature = 'lock:optimistic-add {model} {--namespace=}';

    public $description = 'Add a migration to add optimistic locking columns to a model <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    #[Override]
    public function generateMigrationPath(Model $instance): string
    {
        return sprintf('_%s_optimistic_columns_to_%s.php', $this->operation, $instance->getTable());
    }

    /**
     * Return the stub file path.
     */
    #[Override]
    public function getStubPath(): string
    {
        return module_path('Core', sprintf('Locking/Stubs/%s_optimistic_column_to_table.php.stub', $this->operation));
    }
}
