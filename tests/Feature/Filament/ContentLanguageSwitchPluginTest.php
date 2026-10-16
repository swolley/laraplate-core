<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Http\Middleware\ApplyBackofficeContentLocale;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * The content language of the backoffice is a working choice of this browser session, apart from the
 * language of the interface: it decides which translation the panel reads and writes.
 */
function contentLanguageActor(): User
{
    $actor = User::factory()->create(['lang' => 'en']);

    test()->actingAs($actor, 'admin');
    Filament::setCurrentPanel('admin');

    return $actor;
}

beforeEach(function (): void {
    config(['app.available_locales' => ['en', 'it']]);
});

it('adds the content language item to the admin user menu', function (): void {
    contentLanguageActor();

    expect(Filament::getPanel('admin')->getUserMenuItems())->toHaveKey('content_language');
});

it('stores the chosen content language in the session and leaves the interface language alone', function (): void {
    $actor = contentLanguageActor();

    Livewire::test(Topbar::class)
        ->callAction('content_language', ['locale' => 'it'])
        ->assertRedirect();

    expect(session(ApplyBackofficeContentLocale::SESSION_KEY))->toBe('it')
        ->and($actor->fresh()?->lang)->toBe('en');
});

it('rejects a content language that is not available', function (): void {
    contentLanguageActor();

    Livewire::test(Topbar::class)
        ->callAction('content_language', ['locale' => 'xx'])
        ->assertHasActionErrors(['locale']);

    expect(session(ApplyBackofficeContentLocale::SESSION_KEY))->toBeNull();
});
