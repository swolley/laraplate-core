<?php

declare(strict_types=1);

use Modules\Core\Helpers\LocaleContext;


it('gets and sets locale context', function (): void {
    config(['app.locale' => 'en']);

    LocaleContext::set('it');

    expect(LocaleContext::get())->toBe('it');

    LocaleContext::set('en');

    expect(LocaleContext::get())->toBe('en')
        ->and(LocaleContext::isDefault('en'))->toBeTrue();
});

it('returns available locales from translations helper', function (): void {
    $locales = LocaleContext::getAvailable();

    expect($locales)->toBeArray();
});

it('reports default locale', function (): void {
    config(['app.locale' => 'en']);

    expect(LocaleContext::getDefault())->toBe('en')
        ->and(LocaleContext::isDefault('en'))->toBeTrue();
});
