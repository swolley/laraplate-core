<?php

declare(strict_types=1);

use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

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

it('reports default locale and fallback flag', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => false]);

    expect(LocaleContext::getDefault())->toBe('en')
        ->and(LocaleContext::isFallbackEnabled())->toBeFalse();

    config(['core.translation_fallback_enabled' => true]);

    expect(LocaleContext::isFallbackEnabled())->toBeTrue();
});
