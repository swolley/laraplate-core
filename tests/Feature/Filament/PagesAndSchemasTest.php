<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Modules\Core\Filament\Resources\Roles\Pages\EditRole;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($admin_role);
    $this->actingAs($this->admin);
});

it('wires edit pages to their resources', function (): void {
    $role_resource = new ReflectionProperty(EditRole::class, 'resource');
    $setting_resource = new ReflectionProperty(EditSetting::class, 'resource');
    $user_resource = new ReflectionProperty(EditUser::class, 'resource');

    $role_resource->setAccessible(true);
    $setting_resource->setAccessible(true);
    $user_resource->setAccessible(true);

    expect($role_resource->getValue())->toBe(RoleResource::class)
        ->and($setting_resource->getValue())->toBe(SettingResource::class)
        ->and($user_resource->getValue())->toBe(UserResource::class);
});

it('returns delete header action for edit pages', function (): void {
    $method = new ReflectionMethod(EditRole::class, 'getHeaderActions');
    $method->setAccessible(true);
    $role_actions = $method->invoke(new EditRole());

    $method = new ReflectionMethod(EditSetting::class, 'getHeaderActions');
    $method->setAccessible(true);
    $setting_actions = $method->invoke(new EditSetting());

    $method = new ReflectionMethod(EditUser::class, 'getHeaderActions');
    $method->setAccessible(true);
    $user_actions = $method->invoke(new EditUser());

    expect($role_actions)->toHaveCount(1)
        ->and($setting_actions)->toHaveCount(1)
        ->and($user_actions)->toHaveCount(1)
        ->and($role_actions[0])->toBeInstanceOf(DeleteAction::class)
        ->and($setting_actions[0])->toBeInstanceOf(DeleteAction::class)
        ->and($user_actions[0])->toBeInstanceOf(DeleteAction::class);
});

it('returns no tabs for settings list when only one group exists', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'base']);

    $list_settings = new ListSettings();
    $tabs = $list_settings->getTabs();

    expect($tabs)->toBe([]);
});

it('builds tabs and groups when multiple setting groups exist', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'base']);
    Setting::factory()->persistedWithoutApprovalCapture()->create(['group_name' => 'security']);

    $list_settings = new ListSettings();
    $tabs = $list_settings->getTabs();

    $groups_property = new ReflectionProperty(ListSettings::class, 'groups');
    $groups_property->setAccessible(true);
    $groups = $groups_property->getValue($list_settings);

    expect($tabs)->toHaveKeys(['all', 'base', 'security'])
        ->and($groups)->toHaveCount(1);
});
