<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Seeding\ModelCapabilities;
use Modules\Core\Seeding\ModelCapabilityScanner;
use Modules\Core\Seeding\SeedDefinition;
use Modules\Core\Seeding\SeedReconciler;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Overtrue\LaravelVersionable\VersionStrategy;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as BaseRole;
use Spatie\Permission\PermissionRegistrar;

final class CoreDatabaseSeeder extends Seeder
{
    public const VERSIONING_NAME_PREFIX = 'version_strategy_';

    public const SOFT_DELETES_NAME_PREFIX = 'soft_deletes_';

    public const LOCK_NAME_PREFIX = 'lock_';

    public const OPTIMISTIC_LOCK_NAME_PREFIX = 'optimistic_lock_';

    public const TRANSLATION_FALLBACK_NAME_PREFIX = 'translation_fallback_';

    public const AUTO_TRANSLATE_NAME_PREFIX = 'auto_translate_';

    public const AI_MODERATION_NAME_PREFIX = 'ai_moderation_';

    /**
     * @var Collection<string, BaseRole>
     */
    private Collection $groups;

    /**
     * @return array<string,string>
     */
    public static function getDefaultUserRoles(): array
    {
        return [
            'superadmin' => (string) config('permission.roles.superadmin'),
            'admin' => (string) config('permission.roles.admin'),
            'guest' => (string) config('permission.roles.guest'),
            'system' => (string) config('permission.roles.system'),
        ];
    }

    /**
     * @return array<int, array{name: string, value: mixed, type: SettingTypeEnum, group_name: string, description: string, choices?: array<int, mixed>}>
     */
    public static function runtimeSettingDefinitions(): array
    {
        return [
            self::setting('core.verify_new_user', false, SettingTypeEnum::Boolean, 'auth', 'Require email verification for new users'),
            self::setting('core.enable_user_registration', false, SettingTypeEnum::Boolean, 'auth', 'Enable public user registration'),
            self::setting('core.enable_user_2fa', false, SettingTypeEnum::Boolean, 'auth', 'Enable two-factor authentication'),
            self::setting('core.enable_user_licenses', false, SettingTypeEnum::Boolean, 'auth', 'Enable user license checks'),
            self::setting('core.enable_social_login', false, SettingTypeEnum::Boolean, 'auth', 'Enable social login providers'),
            self::setting('core.locking.unlock_allowed', true, SettingTypeEnum::Boolean, 'locking', 'Allow unlocking locked records'),
            self::setting('core.locking.prevent_modifications_on_locked_objects', false, SettingTypeEnum::Boolean, 'locking', 'Whether saves, deletes, and replicates on locked models should be blocked'),
            self::setting('core.locking.prevent_notifications_to_locked_objects', false, SettingTypeEnum::Boolean, 'locking', 'Prevents notifications to locked records'),
            self::setting('core.dynamic_entities', false, SettingTypeEnum::Boolean, 'core', 'Enable dynamic entities'),
            self::setting('core.dynamic_gridutils', false, SettingTypeEnum::Boolean, 'core', 'Enable dynamic grid utilities'),
            self::setting('core.expose_crud_api', false, SettingTypeEnum::Boolean, 'core', 'Expose CRUD API endpoints'),
            self::setting('core.auto_translate_fallback_to_ai', true, SettingTypeEnum::Boolean, 'translations', 'Fallback to AI translation when the primary provider fails'),
            self::setting('core.translation_cache_enabled', true, SettingTypeEnum::Boolean, 'translations', 'Cache translation results'),
            self::setting('core.auto_translate_provider', 'deepl', SettingTypeEnum::String, 'translations', 'Default translation provider', ['deepl', 'ai']),
            self::setting('core.notifications.approvals.enabled', true, SettingTypeEnum::Boolean, 'approvals', 'Enable pending approval notifications'),
            self::setting('core.notifications.approvals.channels', ['mail'], SettingTypeEnum::Json, 'approvals', 'Approval notification channels', ['mail', 'database']),
            self::setting('core.notifications.approvals.default_threshold_hours', 8, SettingTypeEnum::Integer, 'approvals', 'Default hours before pending approval notification'),
            self::setting('search.features.reranker', true, SettingTypeEnum::Boolean, 'search', 'Enable search reranker'),
            self::setting('search.features.ensemble', true, SettingTypeEnum::Boolean, 'search', 'Enable ensemble search'),
            self::setting('search.reranker.top_k', 30, SettingTypeEnum::Integer, 'search', 'Reranker candidate count'),
            self::setting('search.reranker.weight', 0.5, SettingTypeEnum::Float, 'search', 'Reranker score weight'),
            self::setting('search.vector_search.enabled', true, SettingTypeEnum::Boolean, 'search', 'Enable vector search'),
            self::setting('search.vector_search.dimension', 768, SettingTypeEnum::Integer, 'search', 'Vector search dimensions'),
            self::setting('search.vector_search.similarity', 'cosine', SettingTypeEnum::String, 'search', 'Vector similarity metric', ['cosine', 'dot_product', 'euclidean']),
        ];
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguarded(function (): void {
            $this->defaultSettings();
            $this->defaultPermissions();
            $this->defaultRoles();
            $this->defaultUsers();
            $this->defaultCrons();
        });

        app(SettingsCacheCoordinator::class)->flushAll();
    }

    private static function setting(string $name, mixed $value, SettingTypeEnum $type, string $group, string $description, ?array $choices = null): array
    {
        return [
            'name' => $name,
            'value' => $value,
            'encrypted' => false,
            'choices' => $choices,
            'type' => $type,
            'group_name' => $group,
            'description' => $description,
        ];
    }

    private function defaultPermissions(): void
    {
        // il comando ha già le transaction
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->logOperation((string) config('permission.models.permission'));
        Artisan::call('permission:refresh');
        $this->command->line('    - permissions updated');
    }

    private function defaultRoles(): void
    {
        $user_class = user_class();

        /** @var class-string<BaseRole> $role_class */
        $role_class = config('permission.models.role');
        $role_instance = new ReflectionClass($role_class)->newInstanceWithoutConstructor();
        $role_table = $role_instance->getTable();

        /** @var class-string<Permission> $permission_class */
        $permission_class = config('permission.models.permission');
        $user_instance = new ReflectionClass($user_class)->newInstanceWithoutConstructor();
        $user_table = $user_instance->getTable();

        $this->logOperation($role_class);

        $roles = self::getDefaultUserRoles();

        $all_permissions = $permission_class::query()->get();

        $roles_data = [
            [
                'name' => $roles['superadmin'],
                'description' => 'superadmin is the only one who can bypass all the system guards and permissions',
                'locked_at' => now(),
            ],
            [
                'name' => $roles['admin'],
                'locked_at' => now(),
                'permissions' => fn () => $all_permissions
                    ->filter(function ($permission) use ($user_table, $role_table) {
                        $isUserOrRoleTable = in_array($permission->table_name, [$user_table, $role_table], true);
                        $isSelectAction = str_ends_with($permission->name, '.' . ActionEnum::Select->value);
                        $isLockAction = str_ends_with($permission->name, '.' . ActionEnum::Lock->value);

                        return ($isUserOrRoleTable || $isSelectAction) && ! $isLockAction;
                    }),
            ],
            [
                'name' => $roles['guest'],
                'locked_at' => now(),
                'permissions' => fn () => $all_permissions
                    ->filter(function ($permission) {
                        $isSelectAction = str_ends_with($permission->name, '.' . ActionEnum::Select->value);
                        $excludedTables = ['versions', 'user_grid_configs', 'modifications', 'cron_jobs'];

                        return $isSelectAction && ! in_array($permission->table_name, $excludedTables, true);
                    }),
            ],
            // system is not superadmin so develper can decide what to do with it and limit permissions for security reasons
            [
                'name' => $roles['system'],
                'locked_at' => now(),
                'permissions' => fn () => $all_permissions,
                // ->filter(function ($permission) {
                //     return $permission->name === 'system.permissions';
                // }),
            ],
        ];

        $this->groups = $role_class::query()->withoutGlobalScopes()->whereIn('name', array_column($roles_data, 'name'))->get(['id', 'name', 'guard_name'])->keyBy('name');
        $existing_roles = $this->groups->keys()->all();
        $new_roles = array_filter($roles_data, fn ($role) => ! in_array($role['name'], $existing_roles, true));

        if ($new_roles === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        $role_instance->getConnection()->transaction(function () use ($role_class, $new_roles): void {
            foreach ($new_roles as &$role) {
                $this->create($role_class, $role);
                $this->command->line("    - {$role['name']} <fg=green>created</>");
            }
        });

        // Reload keyed roles so defaultUsers() sees IDs created in this transaction.
        $this->groups = $role_class::query()->withoutGlobalScopes()->whereIn('name', array_column($roles_data, 'name'))->get(['id', 'name', 'guard_name'])->keyBy('name');
    }

    private function defaultUsers(): void
    {
        $user_class = user_class();
        $user_instance = new ReflectionClass($user_class)->newInstanceWithoutConstructor();

        $this->logOperation($user_class);

        $anonymous = config('permission.users.guest');
        $superadmin = config('permission.users.superadmin');
        $admin = config('permission.users.admin');
        $system = config('permission.users.system');

        $users_data = [
            [
                'name' => $superadmin,
                'username' => $superadmin,
                'email' => "{$superadmin}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('superadmin')],
                'locked_at' => now(),
                'valid_from' => now(),
                'valid_to' => null,
            ],
            [
                'name' => $admin,
                'username' => $admin,
                'email' => "{$admin}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('admin')],
                'locked_at' => now(),
                'valid_from' => now(),
                'valid_to' => null,
            ],
            [
                'name' => $anonymous,
                'username' => $anonymous,
                'email' => "{$anonymous}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('guest')],
                'locked_at' => now(),
                'valid_from' => now(),
                'valid_to' => null,
            ],
            [
                'name' => $system,
                'username' => $system,
                'email' => "{$system}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('system')],
                'locked_at' => now(),
                'valid_from' => now(),
                'valid_to' => null,
            ],
        ];

        $seed_usernames = array_column($users_data, 'username');
        $existing_users = $user_class::query()->withoutGlobalScopes()->whereIn('username', $seed_usernames)->get(['id', 'username'])->keyBy('username');
        $new_users = array_filter($users_data, fn ($user) => ! isset($existing_users[$user['username']]));

        if ($new_users === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        $user_instance->getConnection()->transaction(function () use ($user_class, $new_users, $superadmin): void {
            foreach ($new_users as &$user) {
                $this->create($user_class, $user);
                $this->command->line("    - {$user['username']} <fg=green>created</>");

                if ($user['username'] === $superadmin) {
                    $this->command->line("      with password: {$user['password']}");
                }
            }
        });
    }

    private function defaultSettings(): void
    {
        $this->logOperation(Setting::class);

        $core_settings = [
            [
                'name' => 'default_language',
                'value' => config('app.locale'),
                'encrypted' => false,
                'choices' => null,
                'type' => SettingTypeEnum::String,
                'group_name' => 'base',
                'description' => 'Lingua default',
            ],
            [
                'name' => 'pagination',
                'value' => 20,
                'encrypted' => false,
                'choices' => null,
                'type' => SettingTypeEnum::Integer,
                'group_name' => 'base',
                'description' => 'Paginazione default chiamate',
            ],
            [
                'name' => 'max_concurrent_sessions',
                'value' => PHP_INT_MAX,
                'encrypted' => false,
                'choices' => null,
                'type' => SettingTypeEnum::Integer,
                'group_name' => 'base',
                'description' => 'Numero massimo sessioni simultanee',
            ],
        ];

        array_push($core_settings, ...self::runtimeSettingDefinitions());

        $all_model_classes = models();
        $capabilities = app(ModelCapabilityScanner::class)->scan();

        $scanned_classes = array_column($capabilities, 'modelClass');

        foreach (array_diff($all_model_classes, $scanned_classes) as $skipped_class) {
            $this->command->warn("    - skipped {$skipped_class}: capabilities could not be resolved (see log)");
        }

        $default_approval_threshold = (int) config('core.notifications.approvals.default_threshold_hours', 8);

        // Group derived settings by the module that actually owns the model
        // they describe, not by the module running this seeder: a MES model's
        // version_strategy_* row must be reconciled with module = 'MES' so
        // SettingsCleaner can ever classify it Disabled/Absent and remove it.
        /** @var array<string, list<array<string,mixed>>> $settings_by_module */
        $settings_by_module = ['Core' => $core_settings];

        foreach ($capabilities as $capability) {
            $module = $this->moduleForModel($capability->modelClass);
            $settings_by_module[$module] ??= [];
            $this->pushCapabilitySettings($settings_by_module[$module], $capability, $default_approval_threshold);
        }

        $created = 0;
        $realigned = 0;
        $unchanged = 0;

        foreach ($settings_by_module as $module => $rows) {
            if ($rows === []) {
                continue;
            }

            $outcome = app(SeedReconciler::class)->reconcile(
                SeedDefinition::for(Setting::class)
                    ->identity(['name'])
                    ->structural(['type', 'group_name', 'description', 'choices'])
                    ->initial(['value'])
                    ->ownedBy($module)
                    ->rows($rows),
            );

            $created += count($outcome->created);
            $realigned += count($outcome->realigned);
            $unchanged += $outcome->unchanged;
        }

        $this->command->line("    - created {$created}, realigned {$realigned}, unchanged {$unchanged}");
    }

    /**
     * Resolve the module that owns a model class, so its derived settings are
     * stamped with that module rather than with the module running this seeder.
     *
     * `Modules\{Name}\Models\...` owns `{Name}`; everything else (notably
     * `App\Models\...`) is treated as Core-owned.
     */
    private function moduleForModel(string $modelClass): string
    {
        $namespace = config('modules.namespace');
        $prefix = (is_string($namespace) ? $namespace : 'Modules') . '\\';

        if (! str_starts_with($modelClass, $prefix)) {
            return 'Core';
        }

        $module_name = strtok(substr($modelClass, strlen($prefix)), '\\');

        return $module_name === false || $module_name === '' ? 'Core' : $module_name;
    }

    /**
     * Append the per-model rows a single {@see ModelCapabilities} entry contributes.
     */
    private function pushCapabilitySettings(array &$defaultSettings, ModelCapabilities $capability, int $defaultApprovalThreshold): void
    {
        $table = $capability->table;
        $instance = new ReflectionClass($capability->modelClass)->newInstanceWithoutConstructor();

        if ($capability->hasVersions) {
            $this->seedVersionedModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::VERSIONING_NAME_PREFIX, $table));
        }

        if ($capability->hasSoftDeletes) {
            $this->seedSoftDeletedModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::SOFT_DELETES_NAME_PREFIX, $table));
        }

        if ($capability->hasLocks) {
            $this->seedLockedModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::LOCK_NAME_PREFIX, $table));
        }

        if ($capability->hasOptimisticLocking) {
            $this->seedOptimisticLockedModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::OPTIMISTIC_LOCK_NAME_PREFIX, $table));
        }

        if ($capability->hasTranslations) {
            $this->seedTranslationFallbackModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::TRANSLATION_FALLBACK_NAME_PREFIX, $table));
            $this->seedAutoTranslateModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::AUTO_TRANSLATE_NAME_PREFIX, $table));
        }

        if ($capability->hasApprovals) {
            $this->seedAiModerationModel($defaultSettings, $instance, $table, $this->getSettingKeyName(self::AI_MODERATION_NAME_PREFIX, $table));

            $defaultSettings[] = [
                'name' => "approval_threshold__{$table}",
                'value' => $defaultApprovalThreshold,
                'encrypted' => false,
                'choices' => null,
                'type' => SettingTypeEnum::Integer,
                'group_name' => 'approvals',
                'description' => "Hours before notification for pending {$table} approvals",
            ];
        }
    }

    private function getSettingKeyName(string $prefix, string $suffix): string
    {
        return PerModelSettingResolver::nameFor($prefix, $suffix);
    }

    private function seedVersionedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'versionStrategy')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => VersionStrategy::DIFF,
            'encrypted' => false,
            'choices' => [false, ...VersionStrategy::cases()],
            'type' => SettingTypeEnum::Json,
            'group_name' => 'versioning',
            'description' => "Version strategy for {$table}",
        ];
    }

    private function seedSoftDeletedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'softDeletesEnabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'soft_deletes',
            'description' => "Soft deletes status for {$table}",
        ];
    }

    private function seedLockedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'locksEnabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'locking',
            'description' => "Lock status for {$table}",
        ];
    }

    private function seedOptimisticLockedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'optimisticLocksEnabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'locking',
            'description' => "Optimistic lock status for {$table}",
        ];
    }

    private function seedTranslationFallbackModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'translation_fallback_enabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'translations',
            'description' => "Translation fallback for {$table}",
        ];
    }

    private function seedAutoTranslateModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'auto_translate_enabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => false,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'translations',
            'description' => "Auto-translate for {$table}",
        ];
    }

    private function seedAiModerationModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'ai_moderation_enabled')) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => false,
            'encrypted' => false,
            'choices' => null,
            'type' => SettingTypeEnum::Boolean,
            'group_name' => 'moderation',
            'description' => "AI moderation for {$table}",
        ];
    }

    private function defaultCrons(): void
    {
        $this->logOperation(CronJob::class);
        $cron_job_model = new CronJob;

        $default_crons = [
            [
                'name' => 'clearUserAssignedLicenses',
                'command' => 'auth:clear-licenses',
                'parameters' => [],
                'schedule' => '@midnight',
                'description' => 'Resetta assegnazione licenze login a utenti',
                'is_active' => (bool) config('core.enable_user_licenses', false),
            ],
            [
                'name' => 'clearResetTokens',
                'command' => 'auth:clear-resets',
                'parameters' => [],
                'schedule' => '*/4 * * * *',
                'description' => 'Rimuove reset password tokens scaduti',
                'is_active' => true,
            ],
            [
                'name' => 'checkPendingApprovals',
                'command' => 'approvals:check-pending',
                'parameters' => [],
                'schedule' => '0 */4 * * *',
                'description' => 'Controlla e notifica record in attesa di approvazione',
                'is_active' => false,
            ],
        ];

        $existing_crons = CronJob::query()->withoutGlobalScopes()
            ->pluck('name')
            ->flip()
            ->all();

        $new_crons = array_filter(
            $default_crons,
            fn ($cron) => ! isset($existing_crons[$cron['name']]),
        );

        if ($new_crons === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        $cron_job_model->getConnection()->transaction(function () use ($new_crons): void {
            foreach ($new_crons as &$cron) {
                if (! CronJob::query()->withoutGlobalScopes()->where('name', $cron['name'])->exists()) {
                    $this->create(CronJob::class, $cron);
                    $this->command->line("    - {$cron['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$cron['name']} already exists");
                }
            }
        });
    }
}
