<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Console\View\Components\Task;
use Illuminate\Console\View\Components\TwoColumnDetail;
use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Modules\Core\Console\View\Components\MigrationTask;
use Modules\Core\Helpers\MigrationModuleResolver;
use Override;

class Migrator extends BaseMigrator
{
    protected ?string $current_migration_file = null;

    #[Override]
    protected function runUp($file, $batch, $pretend): void
    {
        $this->current_migration_file = $file;

        try {
            parent::runUp($file, $batch, $pretend);
        } finally {
            $this->current_migration_file = null;
        }
    }

    #[Override]
    protected function runDown($file, $migration, $pretend): void
    {
        $this->current_migration_file = $file;

        try {
            parent::runDown($file, $migration, $pretend);
        } finally {
            $this->current_migration_file = null;
        }
    }

    #[Override]
    protected function write($component, ...$arguments): void
    {
        if ($this->output && $component === Task::class && isset($arguments[0])) {
            $module = $this->current_migration_file !== null
                ? MigrationModuleResolver::resolveFromPath($this->current_migration_file)
                : MigrationModuleResolver::resolveFromName((string) $arguments[0]);

            (new MigrationTask($this->output))->render(
                $arguments[0],
                $module,
                $arguments[1] ?? null,
            );

            return;
        }

        if ($this->output && $component === TwoColumnDetail::class && isset($arguments[0])) {
            $module = $this->current_migration_file !== null
                ? MigrationModuleResolver::resolveFromPath($this->current_migration_file)
                : MigrationModuleResolver::resolveFromName((string) $arguments[0]);

            $module_badge = sprintf('<fg=cyan;options=bold>[%s]</>', $module);

            if (isset($arguments[1])) {
                $arguments[1] = $module_badge . ' ' . $arguments[1];
            } else {
                $arguments[1] = $module_badge;
            }
        }

        parent::write($component, ...$arguments);
    }
}
