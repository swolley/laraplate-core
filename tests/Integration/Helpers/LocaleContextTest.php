<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Modules\Core\Helpers\LocaleContext;

beforeEach(function (): void {
    LocaleContext::resetDefaultLocaleCache();
});

it('reads and writes locale context', function (): void {
    App::setLocale('it');

    expect(LocaleContext::get())->toBe('it');
    expect(LocaleContext::isDefault('it'))->toBe(config('app.locale') === 'it');
    expect(LocaleContext::getDefault())->toBe(config('app.locale'));
    expect(LocaleContext::getAvailable())->toBe(translations());

    LocaleContext::set('en');
    expect(App::getLocale())->toBe('en');
});

it('caches default locale until reset', function (): void {
    $default = config('app.locale');

    expect(LocaleContext::getDefaultCached())->toBe($default);

    config(['app.locale' => 'fr']);
    expect(LocaleContext::getDefaultCached())->toBe($default);

    LocaleContext::resetDefaultLocaleCache();
    expect(LocaleContext::getDefaultCached())->toBe('fr');
});
