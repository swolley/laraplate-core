<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\SoftDeletes\SoftDeletes;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Seeder;
use Overtrue\LaravelVersionable\VersionStrategy;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as BaseRole;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class CoreDatabaseSeeder extends Seeder
{
    /**
     * @var Collection<string, BaseRole>
     */
    private Collection $groups;

    public const VERSIONING_NAME_PREFIX = 'version_strategy_';

    public const SOFT_DELETES_NAME_PREFIX = 'soft_deletes_';

    public const LOCK_NAME_PREFIX = 'lock_';

    public const OPTIMISTIC_LOCK_NAME_PREFIX = 'optimistic_lock_';

    /**
     * @return array<string,string>
     */
    public static function getDefaultUserRoles(): array
    {
        return [
            'superadmin' => (string) config('permission.roles.superadmin'),
            'admin' => (string) config('permission.roles.admin'),
            'guest' => (string) config('permission.roles.guest'),
        ];
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguarded(function (): void {
            $this->defaultSettings();
            $this->defaultApprovalSettings();
            $this->defaultPermissions();
            $this->defaultRoles();
            $this->defaultUsers();
            $this->defaultCrons();
        });

        Artisan::call('cache:clear');
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

        $roles_data = [
            [
                'name' => $roles['superadmin'],
                'description' => 'superadmin is the only one who can bypass all the system guards and permissions',
                'locked_at' => now(),
            ],
            [
                'name' => $roles['admin'],
                'locked_at' => now(),
                'permissions' => fn () => $permission_class::query()->where(function ($query) use ($user_table, $role_table): void {
                    $query->whereIn('table_name', [$user_table, $role_table])
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value);
                })->whereNot('name', 'like', '%.' . ActionEnum::LOCK->value)->get(),
            ],
            [
                'name' => $roles['guest'],
                'locked_at' => now(),
                'permissions' => fn () => $permission_class::query()->where('name', 'like', '%.' . ActionEnum::SELECT->value)
                    ->whereNotIn('table_name', ['versions', 'user_grid_configs', 'modifications', 'cron_jobs'])
                    ->get(),
            ],
        ];

        $this->groups = $role_class::query()->withoutGlobalScopes()->whereIn('name', array_column($roles_data, 'name'))->get(['id', 'name', 'guard_name'])->keyBy('name');
        $existing_roles = $this->groups->keys()->all();
        $new_roles = array_filter($roles_data, fn ($role) => ! in_array($role['name'], $existing_roles, true));

        if ($new_roles === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        DB::transaction(function () use ($role_class, $new_roles): void {
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

        $this->logOperation($user_class);

        $anonymous = config('permission.users.guest');
        $superadmin = config('permission.users.superadmin');
        $admin = config('permission.users.admin');

        $users_data = [
            [
                'name' => $superadmin,
                'username' => $superadmin,
                'email' => "{$superadmin}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('superadmin')],
            ],
            [
                'name' => $admin,
                'username' => $admin,
                'email' => "{$admin}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('admin')],
            ],
            [
                'name' => $anonymous,
                'username' => $anonymous,
                'email' => "{$anonymous}@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Str::random(16),
                'email_verified_at' => now(),
                'roles' => [$this->groups->get('guest')],
            ],
        ];

        $existing_users = $user_class::query()->withoutGlobalScopes()->whereIn('username', [$anonymous, $superadmin, $admin])->get(['id', 'username'])->keyBy('username');
        $new_users = array_filter($users_data, fn ($user) => ! isset($existing_users[$user['username']]));

        if ($new_users === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        DB::transaction(function () use ($user_class, $new_users, $superadmin): void {
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

        $default_settings = [
            [
                'name' => 'default_language',
                'value' => config('app.locale'),
                'type' => SettingTypeEnum::STRING,
                'group_name' => 'base',
                'description' => 'Lingua default',
            ],
            [
                'name' => 'pagination',
                'value' => 20,
                'type' => SettingTypeEnum::INTEGER,
                'group_name' => 'base',
                'description' => 'Paginazione default chiamate',
            ],
            [
                'name' => 'max_concurrent_sessions',
                'value' => PHP_INT_MAX,
                'type' => SettingTypeEnum::INTEGER,
                'group_name' => 'base',
                'description' => 'Numero massimo sessioni simultanee',
            ],
        ];

        $to_remove_settings = [];

        $all_models = models();

        foreach ($all_models as $model) {
            $reflected = new ReflectionClass($model);
            $instance = $reflected->newInstanceWithoutConstructor();
            $table = $instance->getTable();

            // versioned models

            $versioned_setting_key_name = $this->getSettingKeyName(self::VERSIONING_NAME_PREFIX, $table);
            if (class_uses_trait($model, HasVersions::class)) {
                $this->seedVersionedModel($default_settings, $instance, $table, $versioned_setting_key_name);
            } else {
                $to_remove_settings[] = $versioned_setting_key_name;
            }

            // soft deletes models
            
            $soft_deletes_setting_key_name = $this->getSettingKeyName(self::SOFT_DELETES_NAME_PREFIX, $table);
            if (class_uses_trait($model, SoftDeletes::class)) {
                $this->seedSoftDeletedModel($default_settings, $instance, $table, $soft_deletes_setting_key_name);
            } else {
                $to_remove_settings[] = $soft_deletes_setting_key_name;
            }

            // locked models
            
            $locked_model_key_name = $this->getSettingKeyName(self::LOCK_NAME_PREFIX, $table);
            if (class_uses_trait($model, HasLocks::class)) {
                $this->seedLockedModel($default_settings, $instance, $table, $locked_model_key_name);
            } else {
                $to_remove_settings[] = $locked_model_key_name;
            }

            // optimistic locked models
            
            $optimistic_locked_model_key_name = $this->getSettingKeyName(self::OPTIMISTIC_LOCK_NAME_PREFIX, $table);
            if (class_uses_trait($model, HasOptimisticLocking::class)) {
                $this->seedOptimisticLockedModel($default_settings, $instance, $table, $optimistic_locked_model_key_name);
            } else {
                $to_remove_settings = $optimistic_locked_model_key_name;
            }
        }

        if ($to_remove_settings !== []) {
            $this->deleteRefuses($to_remove_settings);
        }

        $existing_settings = Setting::withoutGlobalScopes()
            ->whereIn('name', array_column($default_settings, 'name'))
            ->select(['name'])
            ->pluck('name')
            ->flip()
            ->all();

        $new_settings = array_filter(
            $default_settings,
            fn ($setting) => ! isset($existing_settings[$setting['name']]),
        );

        if ($new_settings === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        DB::transaction(function () use ($new_settings): void {
            foreach ($new_settings as &$setting) {
                if (! Setting::query()->withoutGlobalScopes()->where('name', $setting['name'])->exists()) {
                    $this->create(Setting::class, $setting);
                    $this->command->line("    - {$setting['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$setting['name']} already exists");
                }
            }
        });
    }

    private function deleteRefuses(array $list): void
    {
        Setting::query()->whereIn('name', $list)->forceDelete();
    }

    private function getSettingKeyName(string $prefix, string $suffix): string
    {
        return "{$prefix}_{$suffix}";
    }

    private function seedVersionedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'versionStrategy') && $model->versionStrategy === false) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => VersionStrategy::DIFF,
            'type' => SettingTypeEnum::JSON,
            'group_name' => 'versioning',
            'description' => "Version strategy for {$table}",
            'choices' => [false, ...VersionStrategy::cases()],
        ];
    }

    private function seedSoftDeletedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'softDeletesEnabled') && $model->softDeletesEnabled === false) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'type' => SettingTypeEnum::BOOLEAN,
            'group_name' => 'soft_deletes',
            'description' => "Soft deletes status for {$table}",
        ];
    }

    private function seedLockedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'locksEnabled') && $model->locksEnabled === false) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'type' => SettingTypeEnum::BOOLEAN,
            'group_name' => 'locking',
            'description' => "Lock status for {$table}",
        ];
    }

    private function seedOptimisticLockedModel(array &$defaultSettings, Model $model, string $table, string $keyName): void
    {
        if (property_exists($model, 'optimisticLocksEnabled') && $model->optimisticLocksEnabled === false) {
            return;
        }

        $defaultSettings[] = [
            'name' => $keyName,
            'value' => true,
            'type' => SettingTypeEnum::BOOLEAN,
            'group_name' => 'locking',
            'description' => "Optimistic lock status for {$table}",
        ];
    }

    private function defaultCrons(): void
    {
        $this->logOperation(CronJob::class);

        $default_crons = [
            [
                'name' => 'clearUserAssignedLicenses',
                'command' => 'auth:clear-licenses',
                'parameters' => [],
                'schedule' => '@midnight',
                'description' => 'Resetta assegnazione licenze login a utenti',
                'is_active' => config('auth.enable_user_licenses'),
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

        $existing_crons = CronJob::withoutGlobalScopes()
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

        DB::transaction(function () use ($new_crons): void {
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

    /**
     * Create approval threshold settings for all models using HasApprovals trait.
     * Auto-discovers models by scanning all module Model directories.
     */
    private function defaultApprovalSettings(): void
    {
        $this->command->info('  Seeding approval threshold settings...');

        $models_with_approvals = $this->getModelsWithApprovals();

        if ($models_with_approvals === []) {
            $this->command->line('    - no models with HasApprovals found');

            return;
        }

        $default_threshold = config('core.notifications.approvals.default_threshold_hours', 8);
        $approval_settings = [];

        foreach ($models_with_approvals as $table => $model_class) {
            $approval_settings[] = [
                'name' => "approval_threshold_{$table}",
                'value' => $default_threshold,
                'type' => SettingTypeEnum::INTEGER,
                'group_name' => 'approvals',
                'description' => "Hours before notification for pending {$table} approvals",
            ];
        }

        $existing_settings = Setting::withoutGlobalScopes()
            ->whereIn('name', array_column($approval_settings, 'name'))
            ->select(['name'])
            ->pluck('name')
            ->flip()
            ->all();

        $new_settings = array_filter(
            $approval_settings,
            fn ($setting) => ! isset($existing_settings[$setting['name']]),
        );

        if ($new_settings === []) {
            $this->command->line('    - nothing to update');

            return;
        }

        DB::transaction(function () use ($new_settings): void {
            foreach ($new_settings as &$setting) {
                if (! Setting::query()->withoutGlobalScopes()->where('name', $setting['name'])->exists()) {
                    $this->create(Setting::class, $setting);
                    $this->command->line("    - {$setting['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$setting['name']} already exists");
                }
            }
        });
    }

    /**
     * Auto-discover all models that use the HasApprovals trait.
     *
     * @return array<string, class-string<Model>>
     */
    private function getModelsWithApprovals(): array
    {
        $result = [];
        $modules_path = base_path('Modules');

        if (! File::isDirectory($modules_path)) {
            return $result;
        }

        $modules = File::directories($modules_path);

        foreach ($modules as $module_path) {
            $models_path = $module_path . '/app/Models';

            if (! File::isDirectory($models_path)) {
                continue;
            }

            $files = File::files($models_path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class_name = $this->getClassNameFromFile($file->getPathname(), $module_path);

                if ($class_name === null || ! class_exists($class_name)) {
                    continue;
                }

                if ($this->usesHasApprovalsTrait($class_name)) {
                    try {
                        /** @var Model $instance */
                        $instance = new $class_name();
                        $result[$instance->getTable()] = $class_name;
                    } catch (Throwable) {
                        // Skip models that can't be instantiated
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Extract class name from file path.
     */
    private function getClassNameFromFile(string $file_path, string $module_path): ?string
    {
        $module_name = basename($module_path);
        $relative_path = str_replace($module_path . '/app/', '', $file_path);
        $relative_path = str_replace('.php', '', $relative_path);
        $relative_path = str_replace('/', '\\', $relative_path);

        return "Modules\\{$module_name}\\{$relative_path}";
    }

    /**
     * Check if a class uses the HasApprovals trait.
     */
    private function usesHasApprovalsTrait(string $class_name): bool
    {
        try {
            $reflection = new ReflectionClass($class_name);

            // Check direct traits
            $traits = $reflection->getTraitNames();

            if (in_array(HasApprovals::class, $traits, true)) {
                return true;
            }

            // Check parent classes
            $parent = $reflection->getParentClass();

            while ($parent !== false) {
                $parent_traits = $parent->getTraitNames();

                if (in_array(HasApprovals::class, $parent_traits, true)) {
                    return true;
                }

                $parent = $parent->getParentClass();
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
