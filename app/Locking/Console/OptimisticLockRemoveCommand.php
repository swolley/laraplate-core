<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Override;

final class OptimisticLockRemoveCommand extends OptimisticLockAddCommand
{
    #[Override]
    public $signature = 'model:optimistic-lock-remove {model} {--namespace=}';

    #[Override]
    public $description = 'Add a migration to remove optimistic locking columns to a model <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    #[Override]
    protected string $operation = 'remove';

    #[Override]
    protected function updateSettingsTable(string $table): void
    {
        $key_name = CoreDatabaseSeeder::OPTIMISTIC_LOCK_NAME_PREFIX . ".{$table}";
        
        Setting::query()->where('name', $key_name)->forceDelete();
    }
}
