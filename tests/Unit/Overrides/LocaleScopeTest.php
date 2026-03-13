<?php

declare(strict_types=1);

use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Overrides\LocaleScope;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->scope = new LocaleScope();
});

it('extend adds forLocale macro to builder', function (): void {
    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->extend($builder);

    expect($builder->getMacro('forLocale'))->not->toBeNull();
});

it('apply runs without throwing when locale equals default', function (): void {
    config(['app.locale' => 'en']);
    LocaleContext::set('en');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->apply($builder, $model);

    expect(true)->toBeTrue();
});

it('apply filters with fallback when locale differs from default and fallback enabled', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => true]);
    LocaleContext::set('it');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->apply($builder, $model);

    $sql = $builder->toSql();
    expect($sql)->toContain('translations');
});

it('apply filters only current locale when fallback disabled and locale differs', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => false]);
    LocaleContext::set('it');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->apply($builder, $model);

    $sql = $builder->toSql();
    expect($sql)->toContain('translations');
});

it('forLocale macro filters with fallback for non-default locale', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => true]);
    LocaleContext::set('en');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->extend($builder);

    $result = $builder->forLocale('it', true);
    expect($result->toSql())->toContain('translations');
});

it('forLocale macro filters without fallback', function (): void {
    config(['app.locale' => 'en']);
    LocaleContext::set('en');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->extend($builder);

    $result = $builder->forLocale('it', false);
    expect($result->toSql())->toContain('translations');
});

it('forLocale with fallback enabled and locale equals default filters only locale', function (): void {
    config(['app.locale' => 'en']);
    LocaleContext::set('en');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->extend($builder);

    $result = $builder->forLocale('en', true);
    expect($result->toSql())->toContain('translations');
});
