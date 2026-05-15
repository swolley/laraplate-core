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

    /**
     * Run "up" a migration instance.
     *
     * @param  string  $file
     * @param  int  $batch
     * @param  bool  $pretend
     */
    #[Override]
    protected function runUp($file, $batch, $pretend): void // @pest-ignore-type
    {
        $this->current_migration_file = $file;

        try {
            parent::runUp($file, $batch, $pretend);
        } finally {
            $this->current_migration_file = null;
        }
    }

    /**
     * Run "down" a migration instance.
     *
     * @param  string  $file
     * @param  object  $migration
     * @param  bool  $pretend
     */
    #[Override]
    protected function runDown($file, $migration, $pretend): void // @pest-ignore-type
    {
        $this->current_migration_file = $file;

        try {
            parent::runDown($file, $migration, $pretend);
        } finally {
            $this->current_migration_file = null;
        }
    }

    /**
     * Write to the console's output.
     *
     * @param  string  $component
     * @param  array<int, string>|string  ...$arguments
     */
    #[Override]
    protected function write($component, ...$arguments): void // @pest-ignore-type
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
