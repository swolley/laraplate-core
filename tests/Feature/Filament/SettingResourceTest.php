<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;

uses(Tests\TestCase::class)->in('Feature');

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('can list settings', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.settings.index'));

    $response->assertSuccessful();
});

it('can create setting', function (): void {
    $settingData = [
        'name' => 'test_setting',
        'value' => 'test value',
        'type' => 'string',
        'group_name' => 'test',
        'description' => 'Test setting description',
        'is_public' => false,
        'is_encrypted' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('filament.admin.resources.core.settings.create'), $settingData);

    $response->assertSuccessful();

    expect(Setting::where('name', 'test_setting')->first())
        ->not->toBeNull()
        ->and(Setting::where('name', 'test_setting')->first()->value)
        ->toBe('test value');
});

it('can edit setting', function (): void {
    $setting = Setting::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.settings.edit', ['record' => $setting]));

    $response->assertSuccessful();
});

it('can update setting', function (): void {
    $setting = Setting::factory()->create();
    $updateData = [
        'name' => 'updated_setting',
        'value' => 'updated value',
        'type' => 'string',
        'group_name' => 'updated',
        'description' => 'Updated setting description',
        'is_public' => true,
        'is_encrypted' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('filament.admin.resources.core.settings.update', ['record' => $setting]), $updateData);

    $response->assertSuccessful();

    $setting->refresh();
    expect($setting->name)->toBe('updated_setting')
        ->and($setting->value)->toBe('updated value')
        ->and($setting->group_name)->toBe('updated')
        ->and($setting->description)->toBe('Updated setting description');
});

it('can delete setting', function (): void {
    $setting = Setting::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.settings.delete', ['record' => $setting]));

    $response->assertSuccessful();

    expect(Setting::find($setting->id))->toBeNull();
});

it('setting resource has required form fields', function (): void {
    $resource = new SettingResource();
    $schema = $resource->form(new Schema());

    $components = $schema->getComponents();

    expect($components)->toHaveCount(6)
        ->and($components)->toContain('name')
        ->and($components)->toContain('value')
        ->and($components)->toContain('type')
        ->and($components)->toContain('group_name')
        ->and($components)->toContain('description')
        ->and($components)->toContain('is_public')
        ->and($components)->toContain('is_encrypted');
});

it('setting resource has required table columns', function (): void {
    $resource = new SettingResource();
    $table = $resource->table(new Table());

    $columns = $table->getColumns();

    expect($columns)->toHaveCount(8)
        ->and($columns)->toContain('group_name')
        ->and($columns)->toContain('name')
        ->and($columns)->toContain('type')
        ->and($columns)->toContain('value')
        ->and($columns)->toContain('is_public')
        ->and($columns)->toContain('is_encrypted')
        ->and($columns)->toContain('created_at')
        ->and($columns)->toContain('updated_at');
});

it('setting resource has required actions', function (): void {
    $resource = new SettingResource();
    $table = $resource->table(new Table());

    $recordActions = $table->getRecordActions();

    expect($recordActions)->toHaveCount(2)
        ->and($recordActions)->toContain('edit')
        ->and($recordActions)->toContain('delete');
});
