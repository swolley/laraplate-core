<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Str;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Overrides\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Casts\SettingTypeEnum;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Collection;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * @var Collection<string, Role>
     */
    private Collection $groups;

    /**
     * Seed the application's database.
     *
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
    }

    private function defaultPermissions(): void
    {
        // il comando ha già le transaction
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->logOperation(config('permission.models.permission'));
        Artisan::call('permission:refresh');
        $this->command->line("    - permissions updated");
    }

    private function defaultRoles(): void
    {
        $user_class = user_class();
        $role_class = config('permission.models.role');
        $permission_class = config('permission.models.permission');
        $role_table = (new $role_class)->getTable();
        $user_table = (new $user_class)->getTable();

        $this->logOperation($role_class);

        $superadmin = config('permission.roles.superadmin');
        $admin = config('permission.roles.admin');
        $guest = config('permission.roles.guest');

        $roles_data = [
            [
                'name' => $superadmin,
                'locked_at' => now(),
            ],
            [
                'name' => $admin,
                'locked_at' => now(),
                'permissions' => fn() => $permission_class::where(function ($query) use ($user_table, $role_table) {
                    $query->whereIn('table_name', [$user_table, $role_table])
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value);
                })->whereNot('name', 'like', '%.' . ActionEnum::LOCK->value)->get()
            ],
            [
                'name' => $guest,
                'locked_at' => now(),
                'permissions' => fn() => $permission_class::where('name', 'like', '%.' . ActionEnum::SELECT->value)
                    ->whereNotIn('table_name', ['versions', 'user_grid_configs', 'modifications', 'cron_jobs'])
                    ->get()
            ],
        ];

        $this->groups = $role_class::withoutGlobalScopes()->whereIn('name', [$superadmin, $admin, $guest])->get(['id', 'name', 'guard_name'])->keyBy('name');
        $existing_roles = $this->groups->keys()->all();
        $new_roles = array_filter($roles_data, fn($role) => !in_array($role['name'], $existing_roles));

        if (empty($new_roles)) {
            $this->command->line("    - nothing to update");
            return;
        }

        $this->db->transaction(function () use ($role_class, $new_roles) {
            foreach ($new_roles as &$role) {
                $this->create($role_class, $role);
                $this->command->line("    - {$role['name']} <fg=green>created</>");
            }
        });
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
                'email' => "$superadmin@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
                'assignRole' => $this->groups->get('superadmin'),
            ],
            [
                'name' => $admin,
                'username' => $admin,
                'email' => "$admin@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
                'assignRole' => $this->groups->get('admin'),
            ],
            [
                'name' => $anonymous,
                'username' => $anonymous,
                'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
                'assignRole' => $this->groups->get('guest'),
            ],
        ];

        $existing_users = $user_class::withoutGlobalScopes()->whereIn('username', [$anonymous, $superadmin, $admin])->get(['id', 'username'])->keyBy('username');
        $new_users = array_filter($users_data, fn($user) => !isset($existing_users[$user['username']]));

        if (empty($new_users)) {
            $this->command->line("    - nothing to update");
            return;
        }

        $this->db->transaction(function () use ($user_class, $new_users) {
            foreach ($new_users as &$user) {
                $this->create($user_class, $user);
                $this->command->line("    - {$user['username']} <fg=green>created</>");
            }
        });
    }

    private function defaultSettings(): void
    {
        $this->logOperation(Setting::class);

        $default_settings = [
            [
                'name' => 'defaultLanguage',
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
                'name' => 'maxConcurrentSessions',
                'value' => PHP_INT_MAX,
                'type' => SettingTypeEnum::INTEGER,
                'group_name' => 'base',
                'description' => 'Numero massimo sessioni simultanee',
            ],
        ];

        $existing_settings = Setting::withoutGlobalScopes()
            ->whereIn('name', array_column($default_settings, 'name'))
            ->select(['name'])
            ->pluck('name')
            ->flip()
            ->all();

        $new_settings = array_filter(
            $default_settings,
            fn($setting) =>
            !isset($existing_settings[$setting['name']])
        );

        if (empty($new_settings)) {
            $this->command->line("    - nothing to update");
            return;
        }

        $this->db->transaction(function () use ($new_settings) {
            foreach ($new_settings as &$setting) {
                if (!Setting::query()->withoutGlobalScopes()->where('name', $setting['name'])->exists()) {
                    $this->create(Setting::class, $setting);
                    $this->command->line("    - {$setting['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$setting['name']} already exists");
                }
            }
        });
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
                'is_active' => config('core.enable_user_licenses'),
            ],
            [
                'name' => 'clearResetTokens',
                'command' => 'auth:clear-resets',
                'parameters' => [],
                'schedule' => '*/4 * * * *',
                'description' => 'Rimuove reset password tokens scaduti',
                'is_active' => true,
            ],
        ];

        $existing_crons = CronJob::withoutGlobalScopes()
            ->pluck('name')
            ->flip()
            ->all();

        $new_crons = array_filter(
            $default_crons,
            fn($cron) =>
            !isset($existing_crons[$cron['name']])
        );

        if (empty($new_crons)) {
            $this->command->line("    - nothing to update");
            return;
        }

        $this->db->transaction(function () use ($new_crons) {
            foreach ($new_crons as &$cron) {
                if (!CronJob::query()->withoutGlobalScopes()->where('name', $cron['name'])->exists()) {
                    $this->create(CronJob::class, $cron);
                    $this->command->line("    - {$cron['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$cron['name']} already exists");
                }
            }
        });
    }
}
