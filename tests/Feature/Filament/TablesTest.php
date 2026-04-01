<?php

declare(strict_types=1);

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Permissions\Tables\PermissionsTable;
use Modules\Core\Filament\Resources\Settings\Tables\SettingsTable;
use Modules\Core\Filament\Resources\Users\Tables\UsersTable;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $admin */
    $admin = App\Models\User::query()->create(User::factory()->raw([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]));
    $this->admin = $admin;

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($admin_role);
    $this->actingAs($this->admin);
});

it('builds cached distinct options for permissions table filters', function (): void {
    Permission::factory()->create(['guard_name' => 'web']);
    Permission::factory()->create(['guard_name' => 'api']);

    $method = new ReflectionMethod(PermissionsTable::class, 'cachedDistinctOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null, 'guard_name');

    expect($options)->toHaveKey('web')
        ->and($options)->toHaveKey('api');
});

it('builds cached group options for settings filters', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'base']);
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'security']);

    $method = new ReflectionMethod(SettingsTable::class, 'cachedGroupNameOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null);

    expect($options)->toHaveKey('base')
        ->and($options)->toHaveKey('security');
});

it('applies settings default sort callback', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => Setting::query());

    SettingsTable::configure($table);
    $query = $table->getDefaultSort(Setting::query(), 'asc');

    $orders = $query->getQuery()->orders ?? [];
    $order_columns = array_values(array_filter(array_map(static fn (array $order): ?string => $order['column'] ?? null, $orders)));

    expect($order_columns)->toContain('group_name')
        ->and($order_columns)->toContain('name');
});

it('executes users table reset password action closure', function (): void {
    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => User::query());

    UsersTable::configure($table);
    $actions = $table->getFlatRecordActions();
    $action = $actions['reset_password'];
    $callback = $action->getActionFunction();

    $record = new class extends App\Models\User
    {
        public ?string $sent_reset_to = null;

        public function sendPasswordResetNotification($token): void
        {
            $this->sent_reset_to = (string) $token;
        }
    };
    $record->email = 'reset@example.com';

    expect($callback)->not->toBeNull();
    $callback($record);

    expect($record->sent_reset_to)->toBe('reset@example.com');
});
