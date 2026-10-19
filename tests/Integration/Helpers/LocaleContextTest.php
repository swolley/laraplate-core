<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Modules\Core\Helpers\LocaleContext;

it('reads and writes locale context', function (): void {
    App::setLocale('it');

    expect(LocaleContext::get())->toBe('it');
    expect(LocaleContext::getAvailable())->toBe(translations());

    LocaleContext::set('en');
    expect(App::getLocale())->toBe('en');
});

it('keeps the default locale when the request switches language', function (): void {
    config(['app.default_locale' => 'en']);

    // App::setLocale() rewrites app.locale, which is the language of the request, not the default.
    LocaleContext::set('de');

    expect(LocaleContext::getDefault())->toBe('en')
        ->and(LocaleContext::isDefault('en'))->toBeTrue()
        ->and(LocaleContext::isDefault('de'))->toBeFalse();
});

it('reads the content locale the reader picked instead of the language of the request', function (): void {
    LocaleContext::set('de');
    LocaleContext::setContentLocale('it');

    expect(LocaleContext::get())->toBe('it')
        ->and(App::getLocale())->toBe('de')
        ->and(LocaleContext::getContentLocale())->toBe('it');
});

it('moves only the content locale while one is picked, so the interface keeps its language', function (): void {
    LocaleContext::set('de');
    LocaleContext::setContentLocale('it');

    $previous = LocaleContext::get();
    LocaleContext::set('fr');
    $inside = LocaleContext::get();
    LocaleContext::set($previous);

    expect($inside)->toBe('fr')
        ->and(LocaleContext::get())->toBe('it')
        ->and(App::getLocale())->toBe('de');
});

it('follows the language of the request when no content locale is picked', function (): void {
    LocaleContext::set('de');

    expect(LocaleContext::getContentLocale())->toBeNull()
        ->and(LocaleContext::get())->toBe('de');
});

it('follows the default language setting', function (): void {
    config(['app.default_locale' => 'en', 'app.available_locales' => ['en', 'it'], 'core.default_language' => 'it']);

    expect(LocaleContext::getDefault())->toBe('it')
        ->and(LocaleContext::isDefault('it'))->toBeTrue();
});

it('keeps the installation default when the default language setting is not an available locale', function (): void {
    config(['app.default_locale' => 'en', 'app.available_locales' => ['en', 'it'], 'core.default_language' => 'xx']);

    expect(LocaleContext::getDefault())->toBe('en');
});

it('resolves the default locale again when the setting changes', function (): void {
    config(['app.default_locale' => 'en', 'app.available_locales' => ['en', 'it'], 'core.default_language' => 'en']);
    expect(LocaleContext::getDefault())->toBe('en');

    config(['core.default_language' => 'it']);

    expect(LocaleContext::getDefault())->toBe('it')
        ->and(LocaleContext::getDefaultCached())->toBe('it');
});
