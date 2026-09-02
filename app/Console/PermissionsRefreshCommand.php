<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function class_uses_trait;
use function config;
use function models;
use function user_class;

use Approval\Traits\RequiresApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Models\License;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Version;
use Modules\Core\Overrides\Command;
use Modules\Core\Services\Translation\Definitions\ITranslated;
use Modules\Core\Support\PermissionName;
use Override;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Throwable;

final class PermissionsRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'permission:refresh { --p|pretend : prevent changes }';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Refresh the Permission table with inspected rules <fg=green>(⚡ Modules\Core)</fg=green>';

    /**
     * Models the enabled modules keep out of generation, read once from the manifest.
     *
     * @var list<class-string>
     */
    private array $excluded_models = [];

    /**
     * @var array<int,string>
     */
    private static array $MODELS_BLACKLIST = [
        Version::class,
        Modification::class,
        DynamicEntity::class,
        License::class,
        ModelEmbedding::class,
        Pivot::class,
        ITranslated::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $quiet_mode = $this->option('quiet');
        $pretend_mode = $this->option('pretend');

        // Drop stale persistent discovery (e.g. Media moved CMS → Core) but keep
        // in-memory injections used by tests via HelpersCache::setModels().
        HelpersCache::forgetPersistentModels();

        $all_models = models();
        $user_class = user_class();

        $common_permissions = [
            ActionEnum::Select,
            ActionEnum::Insert,
            ActionEnum::Lock,
            // ActionEnum::Unlock,
            ActionEnum::Update,
            ActionEnum::Delete,
            ActionEnum::ForceDelete,
            ActionEnum::Restore,
            ActionEnum::Approve,
            // ActionEnum::Disapprove,
            ActionEnum::Publish,
            // ActionEnum::Unpublish,
        ];

        $changes = false;
        $all_permissions = [];

        /** @var class-string<Permission> $permission_class */
        $permission_class = config('permission.models.permission');
        $permission_model = new $permission_class();
        $connection = $permission_model->getConnection();

        // Resolved here and nowhere else: the manifest exists for this command, so
        // the container binding stays unbuilt for the whole HTTP lifecycle.
        $manifest = app(PermissionManifest::class);
        $this->excluded_models = $manifest->excludedModels();

        // A module may claim a generic verb as a domain operation of its own:
        // `approve` on an ERP return order is a domain step, not the approval
        // workflow the trait provides. Dropping it here because the model lacks
        // the trait, and recreating it from the manifest a moment later, would
        // take its grants and ACLs down with it.
        $declared_permissions = $manifest->names();

        if ($pretend_mode) {
            $this->info('Running in pretend mode, no changes will be made');
            $this->newLine();
        }

        $connection->beginTransaction();

        foreach ($all_models as $model) {
            if (! is_string($model)) {
                continue;
            }

            if (! $this->modelClassExists($model)) {
                continue;
            }
            $need_bypass = $this->checkIfBlacklisted($model);

            if ($need_bypass) {
                if (! $quiet_mode) {
                    $this->line(sprintf("Bypassing '%s' class", $model));
                }

                continue;
            }

            $reflection = new ReflectionClass($model);

            if (! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $instance = $reflection->newInstance();

            // Models without an explicit connection live on whatever `database.default`
            // resolves to, which changes per environment. Naming their permissions after
            // the resolved driver ("mysql.…") would make the same permission unmatchable
            // elsewhere, so the convention pins them to the literal `default` prefix,
            // exactly as PermissionName does for every runtime check.
            $connection_name = $instance->getConnectionName() ?? 'default';
            $table = $instance->getTable();
            $permission_class::flushEventListeners();

            /** @var array<int,string> $found_permissions */
            $found_permissions = $permission_class::query()->where(['connection_name' => $connection_name, 'table_name' => $table])->pluck('name')->toArray();
            $new_model_suffix = $found_permissions !== [] ? ' for new model ' . $model : '';

            foreach ($common_permissions as $permission) {
                $permission_name = PermissionName::build($connection_name, $table, $permission->value);
                $all_permissions[] = $permission_name;

                // permessi di cancellazione logica
                if (($permission === ActionEnum::Delete || $permission === ActionEnum::Restore) && (! class_uses_trait($model, SoftDeletes::class) || $instance->softDeletesEnabled ?? true)) {
                    if (! in_array($permission_name, $declared_permissions, true) && in_array($permission_name, $found_permissions, true) && $permission_class::query()->where('name', $permission_name)->delete()) {
                        if (! $quiet_mode) {
                            $this->line(sprintf("<fg=red>Deleted</> '%s' permission", $permission_name));
                        }

                        $changes = true;
                    }

                    continue;
                }

                // permessi di approvazione
                if ($permission === ActionEnum::Approve && ! class_uses_trait($model, RequiresApproval::class)) {
                    if (! in_array($permission_name, $declared_permissions, true) && in_array($permission_name, $found_permissions, true) && $permission_class::query()->where('name', $permission_name)->delete()) {
                        if (! $quiet_mode) {
                            $this->line(sprintf("<fg=red>Deleted</> '%s' permission", $permission_name));
                        }

                        $changes = true;
                    }

                    continue;
                }

                // permessi di pubblicazione
                if ($permission === ActionEnum::Publish && ! class_uses_trait($model, HasValidity::class)) {
                    if (! in_array($permission_name, $declared_permissions, true) && in_array($permission_name, $found_permissions, true) && $permission_class::query()->where('name', $permission_name)->delete()) {
                        if (! $quiet_mode) {
                            $this->line(sprintf("<fg=red>Deleted</> '%s' permission", $permission_name));
                        }

                        $changes = true;
                    }

                    continue;
                }

                if (! in_array($permission_name, $found_permissions, true)) {
                    $permission = $permission_class::query()->firstOrCreate(
                        ['name' => $permission_name],
                        ['guard_name' => config('auth.defaults.guard', 'web')],
                    );

                    if ($permission->wasRecentlyCreated) {
                        if (! $quiet_mode) {
                            $this->line(sprintf("<fg=green>Created</> '%s' permission %s", $permission_name, $new_model_suffix));
                        }

                        $changes = true;
                    }
                }
            }

            if ($model === $user_class) {
                // solo per gli utenti aggiungo l'impersonificazione
                $permission_name = PermissionName::build($connection_name, $table, ActionEnum::Impersonate->value);
                $all_permissions[] = $permission_name;

                if (! in_array($permission_name, $found_permissions, true)) {
                    $permission = $permission_class::query()->firstOrCreate(
                        ['name' => $permission_name],
                        ['guard_name' => config('auth.defaults.guard', 'web')],
                    );

                    if ($permission->wasRecentlyCreated) {
                        if (! $quiet_mode) {
                            $this->line(sprintf("<fg=green>Created</> '%s' permission %s", $permission_name, $new_model_suffix));
                        }

                        $changes = true;
                    }
                }
            }

            gc_collect_cycles();
        }

        // Domain operations Core cannot infer: posting an invoice, releasing a
        // production order, overriding a workflow. Each module declares its own.
        $permission_class::flushEventListeners();

        foreach ($manifest->names() as $permission_name) {
            $all_permissions[] = $permission_name;

            $permission = $permission_class::query()->firstOrCreate(
                ['name' => $permission_name],
                ['guard_name' => config('auth.defaults.guard', 'web')],
            );

            if ($permission->wasRecentlyCreated) {
                if (! $quiet_mode) {
                    $this->line(sprintf("<fg=green>Created</> '%s' declared permission", $permission_name));
                }

                $changes = true;
            }
        }

        // mappare classi (commentato perché i modelli creati su file system verrebbero eliminati durante un deploy)
        // Permission::firstOrCreate(['name' => 'map_model'], ['name' => 'map_model']);
        // eliminare cache di un modello (commentato perché da decidere in che modo renderla fattibile)
        // Permission::firstOrCreate(['name' => 'flush_cache'], ['name' => 'flush_cache']);

        // Only the verbs this command owns are pruned. A permission name can also
        // come from data a user typed — `sao_workflow_transitions.required_permission`
        // is a free-text column checked with `Gate::allows()` — and deleting one of
        // those would silently forbid the transition forever. Anything whose
        // operation segment this command does not generate is left where it is.
        $managed_operations = array_map(
            static fn (ActionEnum $action): string => $action->value,
            [...$common_permissions, ActionEnum::Impersonate],
        );

        $to_be_deleted = array_values(array_filter(
            $permission_class::query()->whereNotIn('name', $all_permissions)->pluck('name')->toArray(),
            static function (string $name) use ($managed_operations): bool {
                $operation = mb_substr($name, (int) mb_strrpos($name, '.') + 1);

                return in_array($operation, $managed_operations, true);
            },
        ));

        if ($to_be_deleted !== []) {
            foreach (array_chunk($to_be_deleted, 500) as $chunk) {
                $permission_class::query()->whereIn('name', $chunk)->delete();
            }

            $changes = true;

            if (! $quiet_mode) {
                foreach ($to_be_deleted as $permission) {
                    $this->info(sprintf("Deleted '%s' permission", $permission));
                }
            }
        }

        if (! $changes && ! $quiet_mode) {
            $this->newLine();
            $this->info('No changes needed');
        }

        if (! $pretend_mode) {
            $connection->commit();
        } else {
            $connection->rollBack();
        }
    }

    private function checkIfBlacklisted(string $model): bool
    {
        /** @var list<class-string> $config_blacklist */
        $config_blacklist = config('permission.models_blacklist', []);

        $blacklist = array_merge(self::$MODELS_BLACKLIST, $config_blacklist, $this->excluded_models);

        return array_any($blacklist, fn (string $blacklisted): bool => $model === $blacklisted || is_subclass_of($model, $blacklisted));
    }

    /**
     * Autoload may emit a Warning (converted to ErrorException by Laravel) when a
     * cached FQCN points at a moved/deleted file — treat that as "missing".
     */
    private function modelClassExists(string $model): bool
    {
        try {
            return class_exists($model);
        } catch (Throwable) {
            return false;
        }
    }
}
