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

        $roles_Data = [
            [
                'name' => 'superadmin',
                'locked_at' => now(),
            ],
            [
                'name' => 'admin',
                'locked_at' => now(),
                'permissions' => fn() => $permission_class::where(function ($query) use ($user_table, $role_table) {
                    $query->whereIn('table_name', [$user_table, $role_table])
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value);
                })->whereNot('name', 'like', '%.' . ActionEnum::LOCK->value)->get()
            ],
            [
                'name' => 'guest',
                'locked_at' => now(),
                'permissions' => fn() => $permission_class::where('name', 'like', '%.' . ActionEnum::SELECT->value)
                    ->whereNotIn('table_name', ['versions', 'user_grid_configs', 'modifications', 'cron_jobs'])
                    ->get()
            ],
        ];

        $this->groups = $role_class::withoutGlobalScopes()->get()->keyBy('name');
        $existing_roles = $this->groups->keys()->all();
        $new_roles = array_filter($roles_Data, fn($role) => !in_array($role['name'], $existing_roles));

        $this->db->transaction(function () use ($role_class, $permission_class, $role_table, $user_table, $new_roles) {
            foreach ($new_roles as $role) {
                if (!Setting::query()->withoutGlobalScopes()->where('name', $role['name'])->exists()) {
                    $this->create($role_class, $role);
                    $this->command->line("    - {$role['name']} <fg=green>created</>");
                } else {
                    $this->command->line("    - {$role['name']} already exists");
                }
            }
        });
    }

    private function defaultUsers(): void
    {
        $user_class = user_class();

        $this->logOperation($user_class);

        $anonymous = 'anonymous';
        if (!$user_class::whereName($anonymous)->exists()) {
            $anonymous_user = $this->create($user_class, [
                'name' => $anonymous,
                'username' => $anonymous,
                'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Hash::make(config('app.name')),
                'email_verified_at' => now(),
            ]);
            // @phpstan-ignore-next-line
            $anonymous_user->assignRole($this->groups->get('guest'));
            $this->command->line("    - $anonymous <fg=green>created</>");
        } else {
            $this->command->line("    - $anonymous already exists");
        }

        $superadmin = 'superadmin';
        if (!$user_class::whereHas('roles', fn($query) => $query->where('name', $superadmin))->exists()) {
            $this->command->line("    - Creation of a user related to '$superadmin' role is <fg=yellow>strongly suggested</>");
        }

        $admin = 'admin';
        if (!$user_class::whereHas('roles', fn($query) => $query->where('name', $admin))->exists()) {
            $this->command->line("    - Creation of a user related to '$admin' role is <fg=yellow>strongly suggested</>");
        }
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
            ->pluck('name')
            ->flip()
            ->all();

        $new_settings = array_filter(
            $default_settings,
            fn($setting) =>
            !isset($existing_settings[$setting['name']])
        );

        $this->db->transaction(function () use ($new_settings) {
            foreach ($new_settings as $setting) {
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

        $this->db->transaction(function () use ($new_crons) {
            foreach ($new_crons as $cron) {
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
