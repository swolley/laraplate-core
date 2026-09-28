<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * Create and edit pages stay on the record after saving, so the form's cancel button is the way
 * out. It reads "Close" until the form holds unsaved changes and "Cancel" once it does, decided in
 * the browser by the same hash comparison Filament's unsaved-changes alert makes.
 */
function closeOrCancelActor(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');

    return $actor;
}

it('labels the way out by whether the form holds unsaved changes', function (string $page): void {
    closeOrCancelActor();
    $parameters = $page === EditUser::class ? ['record' => User::factory()->create()->getKey()] : [];

    Livewire::test($page, $parameters)
        ->assertSeeHtml('$wire.savedDataHash')
        ->assertSeeHtml('<span x-show="isDirty" x-cloak>Cancel</span><span x-show="! isDirty">Close</span>');
})->with([
    'edit page' => EditUser::class,
    'create page' => CreateUser::class,
]);

it('keeps the plain cancel label when the panel has no unsaved-changes alerts', function (): void {
    closeOrCancelActor();
    Filament::getCurrentPanel()?->unsavedChangesAlerts(false);

    Livewire::test(EditUser::class, ['record' => User::factory()->create()->getKey()])
        ->assertDontSeeHtml('x-show="isDirty"')
        ->assertSeeText('Cancel');
});
