<?php

declare(strict_types=1);

use Modules\Core\Filament\Resources\Media\MediaResource;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Tests\Stubs\Filament\UntranslatedResourceHarness;

/**
 * A resource names itself from `{group}.entities.{table}`: singular and plural model labels, and the
 * navigation label, written as the lang file writes them rather than title-cased by Filament.
 */
it('names a resource from its entities key', function (string $locale, string $singular, string $plural): void {
    app()->setLocale($locale);

    expect(UserResource::getModelLabel())->toBe($singular)
        ->and(UserResource::getPluralModelLabel())->toBe($plural)
        ->and(UserResource::getNavigationLabel())->toBe($plural);
})->with([
    'en' => ['en', 'User', 'Users'],
    'it' => ['it', 'Utente', 'Utenti'],
]);

it('derives the key from the model table without its prefix', function (): void {
    expect(UserResource::getEntityLabelKey())->toBe('app.entities.users')
        ->and(MediaResource::getEntityLabelKey())->toBe('app.entities.media');
});

it('keeps the labels Filament derives when the key is missing', function (): void {
    app()->setLocale('it');

    expect(UntranslatedResourceHarness::getModelLabel())->toBe('setting')
        ->and(UntranslatedResourceHarness::getPluralModelLabel())->toBe('settings')
        ->and(UntranslatedResourceHarness::getNavigationLabel())->toBe('Settings');
});
