<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\DTOs\SettingChangeWarning;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\SettingChangeConfirmations;
use Modules\Core\Tests\Stubs\SettingChangeConfirmationStub;
use Modules\Core\Tests\Support\HttpContext;

uses(RefreshDatabase::class);

function confirmationActor(): void
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

function confirmableSetting(): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'confirm_probe',
        'type' => 'string',
        'value' => 'old',
        'choices' => null,
        'group_name' => 'search',
    ]);
}

function registerConfirmation(?SettingChangeWarning $warning, ?string $locked = null): SettingChangeConfirmationStub
{
    $stub = new SettingChangeConfirmationStub('confirm_probe', $warning, $locked);
    app(SettingChangeConfirmations::class)->register($stub);

    return $stub;
}

it('opens a confirmation instead of saving a changed value', function (): void {
    confirmationActor();
    $stub = registerConfirmation(new SettingChangeWarning('Switch the model?', ['Vectors are deleted.', 'Reindex needed.']));
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'new'])
        ->call('save')
        ->assertActionMounted('confirmSettingChange')
        ->assertMountedActionModalSee(['Switch the model?', 'Vectors are deleted.', 'Reindex needed.']);

    expect($setting->fresh()->value)->toBe('old')
        ->and($stub->confirmedCalls)->toBe(0);
});

it('saves and calls confirmed once when the change is confirmed', function (): void {
    confirmationActor();
    $stub = registerConfirmation(new SettingChangeWarning('Switch?', ['x']));
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'new', 'description' => 'Other'])
        ->call('save')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($setting->fresh()->value)->toBe('new')
        ->and($setting->fresh()->description)->toBe('Other')
        ->and($stub->confirmedCalls)->toBe(1)
        ->and($stub->confirmedValue)->toBe('new');
});

it('saves nothing when the confirmation is cancelled', function (): void {
    confirmationActor();
    $stub = registerConfirmation(new SettingChangeWarning('Switch?', ['x']));
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'new'])
        ->call('save')
        ->unmountAction();

    expect($setting->fresh()->value)->toBe('old')
        ->and($stub->confirmedCalls)->toBe(0);
});

it('saves an unchanged value without a confirmation', function (): void {
    confirmationActor();
    $stub = registerConfirmation(new SettingChangeWarning('Switch?', ['x']));
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['description' => 'Only the description'])
        ->call('save')
        ->assertActionNotMounted('confirmSettingChange');

    expect($setting->fresh()->description)->toBe('Only the description')
        ->and($stub->confirmedCalls)->toBe(0);
});

it('saves as before when no confirmation is registered or warn returns null', function (?SettingChangeWarning $warning, bool $register): void {
    confirmationActor();
    $stub = $register ? registerConfirmation($warning) : null;
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'new'])
        ->call('save')
        ->assertActionNotMounted('confirmSettingChange');

    expect($setting->fresh()->value)->toBe('new')
        ->and($stub?->confirmedCalls ?? 0)->toBe(0);
})->with([
    'not registered' => [null, false],
    'warn returns null' => [null, true],
]);

it('disables the field and shows the reason when the setting is locked', function (): void {
    confirmationActor();
    registerConfirmation(null, 'A reindex is running.');
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertFormFieldDisabled('value')
        ->assertSee('A reindex is running.');
});

it('does not call confirmed when the change is sent for approval', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting);
    Filament::setCurrentPanel('admin');
    $stub = registerConfirmation(new SettingChangeWarning('Switch?', ['x']));
    $setting = confirmableSetting();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'new'])
        ->call('save')
        ->callMountedAction();

    expect($setting->fresh()->value)->toBe('old')
        ->and(Modification::query()->exists())->toBeTrue()
        ->and($stub->confirmedCalls)->toBe(0);
});
