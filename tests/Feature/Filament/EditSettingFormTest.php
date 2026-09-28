<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Filament\Resources\Settings\Schemas\SettingForm;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\HttpContext;

uses(RefreshDatabase::class);

function editSettingActor(): User
{
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $actor */
    $actor = App\Models\User::query()->create(User::factory()->raw());
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');

    return $actor;
}

it('picks the value input from the setting type', function (array $attributes, string $expected_field): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create($attributes);

    expect(SettingForm::valueField($setting->fresh()))->toBeInstanceOf($expected_field);
})->with([
    'boolean' => [['type' => 'boolean', 'value' => true, 'choices' => null], Toggle::class],
    'integer' => [['type' => 'integer', 'value' => 5, 'choices' => null], TextInput::class],
    'float' => [['type' => 'float', 'value' => 1.5, 'choices' => null], TextInput::class],
    'date' => [['type' => 'date', 'value' => '2026-01-01', 'choices' => null], DatePicker::class],
    'string' => [['type' => 'string', 'value' => 'free', 'choices' => null], TextInput::class],
    'string with choices' => [['type' => 'string', 'value' => 'a', 'choices' => ['a', 'b']], Select::class],
    'json list with choices' => [['type' => 'json', 'value' => ['mail'], 'choices' => ['mail', 'database']], CheckboxList::class],
    'json flat list' => [['type' => 'json', 'value' => ['x', 'y'], 'choices' => null], TagsInput::class],
    'json object' => [['type' => 'json', 'value' => ['nested' => ['a' => 1]], 'choices' => null], CodeEditor::class],
]);

it('keeps name, type and encrypted read-only on the edit form', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertFormFieldDisabled('name')
        ->assertFormFieldDisabled('type')
        ->assertFormFieldDisabled('encrypted')
        ->assertFormFieldDisabled('is_internal')
        ->assertFormFieldEnabled('group_name')
        ->assertFormFieldEnabled('value')
        ->assertFormFieldEnabled('description')
        ->assertFormFieldEnabled('is_public')
        ->assertFormFieldDoesNotExist('is_encrypted');
});

it('round trips a json object through the code editor', function (): void {
    $value = ['nested' => ['a' => 1]];

    expect(SettingForm::decodeJson(SettingForm::encodeJson($value)))->toBe($value)
        ->and(SettingForm::decodeJson('  '))->toBeNull();
});

it('sends the edited value to approval with its type and without read-only fields', function (): void {
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'edit_form_integer_setting',
        'type' => 'integer',
        'value' => 5,
        'choices' => null,
        'group_name' => 'base',
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm([
            'name' => 'renamed',
            'value' => '42',
            'description' => 'Updated from the panel',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Change sent for approval');

    $modification = $setting->modifications()->activeOnly()->sole();

    expect($modification->modifications)->not->toHaveKey('name')
        ->and($modification->modifications['value']['modified'])->toBe(42)
        ->and($modification->modifications['description']['modified'])->toBe('Updated from the panel')
        ->and($setting->fresh()->value)->toBe(5);
});

it('saves the value directly when the writer can approve settings', function (): void {
    editSettingActor();
    HttpContext::pretendHttpRequest();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'edit_form_boolean_setting',
        'type' => 'boolean',
        'value' => false,
        'choices' => null,
        'group_name' => 'base',
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => true])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-panels::resources/pages/edit-record.notifications.saved.title'));

    expect($setting->fresh()->value)->toBeTrue()
        ->and($setting->modifications()->activeOnly()->exists())->toBeFalse();
});

it('suggests existing groups and applies a new one without approval', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
        'group_name' => 'base',
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertSeeHtml('<option value="base">')
        ->fillForm(['group_name' => '  brand_new_group  '])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($setting->fresh()->group_name)->toBe('brand_new_group')
        ->and($setting->modifications()->activeOnly()->exists())->toBeFalse();
});

it('requires a group name', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['group_name' => ''])
        ->call('save')
        ->assertHasFormErrors(['group_name' => 'required']);
});

it('offers no create or delete actions on settings', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
    ]);

    Livewire::test(ListSettings::class)
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden(DeleteAction::class, $setting)
        ->assertTableActionHidden(ForceDeleteAction::class, $setting)
        ->assertTableBulkActionHidden(DeleteBulkAction::class)
        ->assertTableBulkActionHidden(ForceDeleteBulkAction::class);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertActionDoesNotExist(DeleteAction::class);

    expect(SettingResource::canCreate())->toBeFalse()
        ->and(SettingResource::canDelete($setting))->toBeFalse()
        ->and(SettingResource::canDeleteAny())->toBeFalse()
        ->and(SettingResource::canForceDelete($setting))->toBeFalse()
        ->and(SettingResource::canForceDeleteAny())->toBeFalse();
});

it('lays out the edit form in rows', function (): void {
    editSettingActor();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
    ]);

    $form = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])->instance()->form;

    $names_by_row = array_map(
        static fn ($component): array => $component instanceof Grid
            ? array_map(
                static fn ($child): ?string => match (true) {
                    $child instanceof Field => $child->getName(),
                    $child instanceof Group => $child->getChildComponents()[0]->getName(),
                    default => null,
                },
                array_values($component->getChildComponents()),
            )
            : [$component->getName()],
        array_values($form->getComponents()),
    );

    expect($names_by_row)->toBe([
        ['name', 'type', 'encrypted', 'is_internal'],
        ['group_name', 'value', 'is_public'],
        ['description'],
    ]);
});

it('sends an is_public change from the form to approval', function (): void {
    // Not editSettingActor(): a superadmin write is never captured, so there would be no
    // modification to read back.
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
        'is_public' => false,
    ]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['is_public' => true])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Change sent for approval');

    expect($setting->modifications()->activeOnly()->sole()->modifications['is_public']['modified'])->toBeTrue()
        ->and($setting->fresh()->is_public)->toBeFalse();
});
