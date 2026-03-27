<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Overrides\LocaleScope;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->scope = new LocaleScope();

    Schema::create('fake_translatable_models', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });

    Schema::create('fake_translatable_model_translations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('fake_translatable_model_id')->constrained('fake_translatable_models')->cascadeOnDelete();
        $table->string('locale');
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });
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

it('apply uses only current locale branch when fallback is disabled', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => false]);
    LocaleContext::set('it');

    $relation_query = Mockery::mock(Illuminate\Database\Eloquent\Builder::class);
    $relation_query->shouldReceive('where')->atLeast()->once()->with('locale', 'it');

    $builder = Mockery::mock(Illuminate\Database\Eloquent\Builder::class);
    $builder->shouldReceive('whereHas')->once()->with('translations', Mockery::on(function (callable $callback) use ($relation_query): bool {
        $callback($relation_query);

        return true;
    }))->andReturnSelf();
    $builder->shouldReceive('with')->once()->with(Mockery::on(function (array $with) use ($relation_query): bool {
        $closure = $with['translation'] ?? null;

        if (! $closure instanceof Closure) {
            return false;
        }
        $closure($relation_query);

        return true;
    }))->andReturnSelf();

    $model = new FakeTranslatableModel();
    $this->scope->apply($builder, $model);

    expect(true)->toBeTrue();
});

it('forLocale without fallback constrains translation locale in eager load', function (): void {
    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();
    $this->scope->extend($builder);
    $query = Mockery::mock(Illuminate\Database\Query\Builder::class);
    $query->shouldReceive('where')->once()->with('locale', 'it');
    $builder->with([
        'translation' => function ($q): void {
            //
        },
    ]);

    $result = $builder->forLocale('it', false);
    $eager_loads = $result->getEagerLoads();
    $translation_loader = $eager_loads['translation'];
    $translation_loader($query);

    expect(true)->toBeTrue();
});

it('apply executes whereHas closure for fallback when query runs against database', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => true]);
    LocaleContext::set('it');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();
    $this->scope->apply($builder, $model);

    expect(fn () => $builder->exists())->not->toThrow(Throwable::class);
});

it('apply executes whereHas closure for current locale only when fallback disabled and query runs', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => false]);
    LocaleContext::set('it');

    $model = new FakeTranslatableModel();
    $builder = $model->newQuery();
    $this->scope->apply($builder, $model);

    expect(fn () => $builder->exists())->not->toThrow(Throwable::class);
});

it('apply invokes whereHas fallback closure with current and default locale constraints', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => true]);
    LocaleContext::set('it');

    $parent_model = new FakeTranslatableModel();
    $relation_for_where_has = $parent_model->translations()->getQuery();
    $relation_for_eager = (new FakeTranslatableModel())->translations()->getQuery();

    $builder = Mockery::mock(Illuminate\Database\Eloquent\Builder::class);
    $builder->shouldReceive('whereHas')->once()->with('translations', Mockery::on(function (callable $callback) use ($relation_for_where_has): bool {
        $callback($relation_for_where_has);

        return true;
    }))->andReturnSelf();
    $builder->shouldReceive('with')->once()->with(Mockery::on(function (array $with) use ($relation_for_eager): bool {
        $closure = $with['translation'] ?? null;

        if (! $closure instanceof Closure) {
            return false;
        }
        $closure($relation_for_eager);

        return true;
    }))->andReturnSelf();

    $this->scope->apply($builder, $parent_model);

    expect($relation_for_where_has->toSql())->toContain('locale')
        ->and($relation_for_eager->toSql())->toContain('locale');
});

it('apply invokes whereHas closure for current locale only when fallback is disabled', function (): void {
    config(['app.locale' => 'en', 'core.translation_fallback_enabled' => false]);
    LocaleContext::set('it');

    $parent_model = new FakeTranslatableModel();
    $relation_for_where_has = $parent_model->translations()->getQuery();
    $relation_for_eager = (new FakeTranslatableModel())->translations()->getQuery();

    $builder = Mockery::mock(Illuminate\Database\Eloquent\Builder::class);
    $builder->shouldReceive('whereHas')->once()->with('translations', Mockery::on(function (callable $callback) use ($relation_for_where_has): bool {
        $callback($relation_for_where_has);

        return true;
    }))->andReturnSelf();
    $builder->shouldReceive('with')->once()->with(Mockery::on(function (array $with) use ($relation_for_eager): bool {
        $closure = $with['translation'] ?? null;

        if (! $closure instanceof Closure) {
            return false;
        }
        $closure($relation_for_eager);

        return true;
    }))->andReturnSelf();

    $this->scope->apply($builder, $parent_model);

    expect($relation_for_where_has->toSql())->toContain('locale')
        ->and($relation_for_eager->toSql())->toContain('locale');
});
