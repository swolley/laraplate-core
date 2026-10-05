<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

function managedFormActor(): void
{
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $actor */
    $actor = App\Models\User::query()->create(User::factory()->raw());
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');
}

it('shows the value of a managed setting read-only and keeps unmanaged ones editable', function (bool $managed): void {
    managedFormActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'integer',
        'value' => 384,
        'choices' => null,
        'group_name' => 'search',
    ]);
    $setting->forceFill(['managed' => $managed])->saveQuietly();

    $page = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);

    $managed ? $page->assertFormFieldDisabled('value') : $page->assertFormFieldEnabled('value');
})->with([
    'managed' => [true],
    'unmanaged' => [false],
]);

it('never stores a value submitted for a managed setting', function (): void {
    managedFormActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'integer',
        'value' => 384,
        'choices' => null,
        'group_name' => 'search',
    ]);
    $setting->forceFill(['managed' => true])->saveQuietly();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 9999, 'description' => 'Edited description'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBe(384)
        ->and($setting->fresh()->description)->toBe('Edited description');
});

it('ignores a value injected into the Livewire state of a managed setting', function (): void {
    managedFormActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'integer',
        'value' => 384,
        'choices' => null,
        'group_name' => 'search',
    ]);
    $setting->forceFill(['managed' => true])->saveQuietly();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->set('data.value', 9999)
        ->call('save');

    expect($setting->fresh()->value)->toBe(384);
});
