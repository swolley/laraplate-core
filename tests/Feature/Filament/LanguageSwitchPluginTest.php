<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Plugins\LanguageSwitchPlugin;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * The backoffice language is the user's own `lang`: the switch writes it, LocalizationMiddleware
 * applies it on the next request, and the SPA, other devices and notifications follow the same value.
 */
function languageSwitchActor(string $lang = 'en'): User
{
    $actor = User::factory()->create(['lang' => $lang]);

    test()->actingAs($actor, 'admin');
    Filament::setCurrentPanel('admin');

    return $actor;
}

beforeEach(function (): void {
    config(['app.available_locales' => ['en', 'it']]);
});

it('offers the available locales under their own names', function (): void {
    expect(LanguageSwitchPlugin::make()->locales())->toBe([
        'en' => 'English',
        'it' => 'Italiano',
    ]);
});

it('adds the language item to the admin user menu', function (): void {
    languageSwitchActor();

    expect(Filament::getPanel('admin')->getUserMenuItems())->toHaveKey('language');
});

it('stores the chosen language on the user and reloads the page', function (): void {
    $actor = languageSwitchActor();

    Livewire::test(Topbar::class)
        ->callAction('language', ['lang' => 'it'])
        ->assertRedirect();

    expect($actor->fresh()?->lang)->toBe('it');
});

it('rejects a language that is not available', function (): void {
    $actor = languageSwitchActor();

    Livewire::test(Topbar::class)
        ->callAction('language', ['lang' => 'xx'])
        ->assertHasActionErrors(['lang']);

    expect($actor->fresh()?->lang)->toBe('en');
});
