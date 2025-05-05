<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Override;
use Illuminate\Database\Eloquent\Model;

class OptimisticLockAddCommand extends LockedAddCommand
{
    public $signature = 'lock:optimistic-add {model} {--namespace=}';

    public $description = 'Add a migration to add optimistic locking columns to a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function generateMigrationPath(Model $instance)
    {
        return "_{$this->operation}_optimistic_columns_to_{$instance->getTable()}.php";
    }

    /**
     * Return the stub file path.
     */
    #[Override]
    public function getStubPath(): string
    {
        return module_path('Core', "Locking/Stubs/{$this->operation}_optimistic_column_to_table.php.stub");
    }
}
