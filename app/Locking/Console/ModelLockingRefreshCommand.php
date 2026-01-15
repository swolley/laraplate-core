<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use function Laravel\Prompts\confirm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Locking\HasOptimisticLocking;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Command;
use ReflectionClass;

final class ModelLockingRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lock:refresh { --quiet: prevent output }';

    /**
     * The console command description.
     */
    protected $description = 'Dynamically generate missing migrations for locking functionalities. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    private bool $quiet_mode = false;

    private bool $changes = false;

    /**
     * @var array<int,string>
     */
    private array $models_blacklist = [];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->quiet_mode = $this->option('quiet');

        $all_models = models();
        $this->changes = false;

        foreach ($all_models as $model) {
            $this->checkModel($model);
        }

        if (! $this->changes && ! $this->quiet_mode) {
            $this->info('No changes needed');
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function checkModel(string $model): void
    {
        $need_bypass = $this->checkIfBlacklisted($model);

        if ($need_bypass) {
            if (! $this->quiet_mode) {
                $this->line(sprintf("Bypassing '%s' class", $model));
            }

            return;
        }

        /** @var Model $instance */
        $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
        $table = $instance->getTable();

        $this->optimisticLockingCheck($instance, $model, $table);
        $this->lockableCheck($instance, $model, $table);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function checkIfBlacklisted(string $model): bool
    {
        return array_any($this->models_blacklist, fn ($blacklisted): bool => $model === $blacklisted || is_subclass_of($model, $blacklisted));
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function optimisticLockingCheck(Model $instance, string $model, string $table): void
    {
        $optimistic_locking_column = method_exists($instance, 'lockVersionColumn') ? $instance->lockVersionColumn() : null;
        $has_optimistic_locking = class_uses_trait($instance, HasOptimisticLocking::class);

        $has_optimistic_locking_column = $optimistic_locking_column !== null && Schema::hasColumn($table, $optimistic_locking_column);

        if ($optimistic_locking_column && $has_optimistic_locking_column && ! $has_optimistic_locking) {
            $this->doRemoveOptimisticLockingOnModel($model, $optimistic_locking_column);
        } elseif ($optimistic_locking_column && $has_optimistic_locking && ! $has_optimistic_locking_column) {
            $this->doAddOptimisticLockingOnModel($model, $optimistic_locking_column);
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function doAddOptimisticLockingOnModel(string $model, string $optimistic_locking_column): void
    {
        if ($this->askConfirmForOperation(
            sprintf('Model %s uses optimistic locking but column %s is missing. Would you like to create it into the schema?', $model, $optimistic_locking_column),
            $model,
            fn (): int => $this->call('lock:optimistic-add', ['model' => $model]),
        )) {
            $this->changes = true;
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function doRemoveOptimisticLockingOnModel(string $model, string $optimistic_locking_column): void
    {
        if ($this->askConfirmForOperation(
            sprintf("Model %s doesn't use optimistic locking but column %s found. Would you like to remove it from the schema?", $model, $optimistic_locking_column),
            $model,
            fn (): int => $this->call('lock:optimistic-remove', ['model' => $model]),
        )) {
            $this->changes = true;
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function lockableCheck(Model $instance, string $model, string $table): void
    {
        $locked_class = HasLocks::class;
        $lock_at_column = method_exists($instance, 'lockedAtColumn') ? $instance->lockedAtColumn() : null;
        $lock_by_column = method_exists($instance, 'lockedByColumn') ? $instance->lockedByColumn() : null;
        $has_locking = class_uses_trait($instance, $locked_class);

        $has_locked_at_column = $lock_at_column !== null && Schema::hasColumn($table, $lock_at_column);
        $has_locked_by_column = $lock_by_column !== null && Schema::hasColumn($table, $lock_by_column);

        if ($lock_at_column && ($has_locked_at_column || $has_locked_by_column) && ! $has_locking) {
            $this->doRemoveLockableOnModel($model, $lock_at_column);
        } elseif ($lock_at_column && $has_locking && (! $has_locked_at_column || ! $has_locked_by_column)) {
            $this->doAddLockableOnModel($model, $lock_at_column);
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function doAddLockableOnModel(string $model, string $lock_at_column): void
    {
        if ($this->askConfirmForOperation(
            sprintf('Model %s uses locks but column %s is missing. Would you like to create it into the schema?', $model, $lock_at_column),
            $model,
            fn (): int => $this->call('lock:add', ['model' => $model]),
        )) {
            $this->changes = true;
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function doRemoveLockableOnModel(string $model, string $lock_at_column): void
    {
        if ($this->askConfirmForOperation(
            sprintf("Model %s doesn't use locks but column %s found. Would you like to remove it from the schema?", $model, $lock_at_column),
            $model,
            fn (): int => $this->call('lock:remove', ['model' => $model]),
        )) {
            $this->changes = true;
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function askConfirmForOperation(string $confirmText, string $model, callable $operation): bool
    {
        if (confirm($confirmText)) {
            $operation();

            return true;
        }

        if (! $this->quiet_mode) {
            $this->line(sprintf("Ignoring '%s' class", $model));
        }

        return false;
    }
}
