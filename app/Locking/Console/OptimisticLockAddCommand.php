<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Override;

class OptimisticLockAddCommand extends LockedAddCommand
{
    #[Override]
    public $signature = 'model:optimistic-lock-add {model} {--namespace=}';

    #[Override]
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

    #[Override]
    protected function updateSettingsTable(string $table): void
    {
        $key_name = CoreDatabaseSeeder::OPTIMISTIC_LOCK_NAME_PREFIX . ".{$table}";

        Setting::query()->insertOrIgnore([
            'name' => $key_name,
            'value' => true,
            'type' => SettingTypeEnum::BOOLEAN,
            'group_name' => 'locking',
            'description' => "Lock status for {$table}",
        ]);
    }
}
