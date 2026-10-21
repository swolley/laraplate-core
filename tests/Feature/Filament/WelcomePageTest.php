<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Welcome;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

function welcomePageActor(): void
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');
}

it('shows the application version and description, and no big logo', function (): void {
    welcomePageActor();

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    Livewire::test(Welcome::class)
        ->assertOk()
        ->assertSee((string) $composer['description'])
        ->assertSee(version())
        ->assertDontSeeHtml('w-90 h-90');
});

it('puts the brand icon beside each module name', function (): void {
    welcomePageActor();

    Livewire::test(Welcome::class)
        ->assertSeeHtml(mb_rtrim((string) config('app.brand_icons'), '/') . '/core.svg')
        ->assertSeeHtml(mb_rtrim((string) config('app.brand_icons'), '/') . '/laraplate.svg');
});
