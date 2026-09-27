<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Filament\Resources\Licenses\Pages\ListLicenses;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $actor */
    $actor = App\Models\User::query()->create(User::factory()->raw());
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    $this->actingAs($actor);
    Filament::setCurrentPanel('admin');
});

it('hides the licenses resource when user licenses are disabled', function (): void {
    config(['core.auth.licenses.enabled' => false]);

    expect(LicenseResource::canAccess())->toBeFalse();

    Livewire::test(ListLicenses::class)->assertForbidden();
});

it('shows the licenses resource when user licenses are enabled', function (): void {
    config(['core.auth.licenses.enabled' => true]);

    expect(LicenseResource::canAccess())->toBeTrue();

    Livewire::test(ListLicenses::class)->assertOk();
});
