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
