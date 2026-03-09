<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Overrides\LocaleScope;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->scope = new LocaleScope();
});

it('extend adds forLocale macro to builder', function (): void {
    $model = new \Modules\Core\Tests\Fixtures\FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->extend($builder);

    expect($builder->getMacro('forLocale'))->not->toBeNull();
});

it('apply runs without throwing when locale equals default', function (): void {
    config(['app.locale' => 'en']);
    \Modules\Core\Helpers\LocaleContext::set('en');

    $model = new \Modules\Core\Tests\Fixtures\FakeTranslatableModel();
    $builder = $model->newQuery();

    $this->scope->apply($builder, $model);

    expect(true)->toBeTrue();
});
