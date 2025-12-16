<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Tests\TestCase;

uses(TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $admin->roles()->attach($adminRole);

    /** @var User $admin */
    /** @var TestCase $this */
    $this->actingAs($admin);
});

test('can list licenses', function (): void {
    /** @var TestCase $this */
    $response = $this->get(route('filament.admin.resources.core.licenses.index'));

    $response->assertSuccessful();
});

test('can create license', function (): void {
    $licenseData = [
        'id' => uuid_create(),
        'valid_from' => now(),
        'valid_to' => now()->addYear(),
    ];

    /** @var TestCase $this */
    $response = $this->post(route('filament.admin.resources.core.licenses.create'), $licenseData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where([
        'id' => $licenseData['id'],
        'valid_from' => $licenseData['valid_from'],
        'valid_to' => $licenseData['valid_to'],
    ])->exists())->toBeTrue();
});

test('can edit license', function (): void {
    $license = License::factory()->create();

    /** @var TestCase $this */
    $response = $this->get(route('filament.admin.resources.core.licenses.edit', ['record' => $license]));

    $response->assertSuccessful();
});

test('can update license', function (): void {
    $license = License::factory()->create();
    $updateData = [
        'valid_to' => now()->addYears(2),
    ];

    /** @var TestCase $this */
    $response = $this->put(route('filament.admin.resources.core.licenses.update', ['record' => $license]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where([
        'id' => $license->id,
        'valid_from' => $license->valid_from,
        'valid_to' => $updateData['valid_to'],
    ])->exists())->toBeTrue();
});

test('can delete license', function (): void {
    $license = License::factory()->create();

    /** @var TestCase $this */
    $response = $this->delete(route('filament.admin.resources.core.licenses.delete', ['record' => $license]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where('id', $license->id)->exists())->toBeFalse();
});

test('can validate license', function (): void {
    $license = License::factory()->create();

    /** @var TestCase $this */
    $response = $this->post(route('filament.admin.resources.core.licenses.validate', ['record' => $license]));

    $response->assertSuccessful();
});

test('license resource has required form fields', function (): void {
    $resource = new LicenseResource();
    $form = $resource->form(new Schema());

    $fields = [
        'name' => TextInput::class,
        'key' => TextInput::class,
        'domain' => TextInput::class,
        'email' => TextInput::class,
        'company' => TextInput::class,
        'phone' => TextInput::class,
        'address' => TextInput::class,
        'city' => TextInput::class,
        'state' => TextInput::class,
        'zip' => TextInput::class,
        'country' => TextInput::class,
        'expires_at' => DatePicker::class,
        'is_active' => Toggle::class,
        'notes' => Textarea::class,
    ];

    foreach ($fields as $field => $component) {
        $formComponent = $form->getComponent($field);
        expect($formComponent)->toBeInstanceOf($component);
    }
});

test('license resource has required table columns', function (): void {
    $resource = new LicenseResource();
    $table = $resource->table(new Table());

    $columns = [
        'name' => TextColumn::class,
        'domain' => TextColumn::class,
        'email' => TextColumn::class,
        'company' => TextColumn::class,
        'expires_at' => TextColumn::class,
        'is_active' => IconColumn::class,
    ];

    foreach ($columns as $column => $type) {
        $tableColumn = $table->getColumn($column);
        expect($tableColumn)->toBeInstanceOf($type);
    }
});

test('license resource has required actions', function (): void {
    $resource = new LicenseResource();
    $table = $resource->table(new Table());

    $actions = $table->getRecordActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
    expect($actions)->toHaveKey('validate');
});
