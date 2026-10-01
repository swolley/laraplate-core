<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Tests\Stubs\Search\LocaleHiddenSearchStubModel;

/**
 * A reindex must reach every row, as `scout:import` does through
 * `makeAllSearchableUsing`. `LocaleScope` hides a row with no translation in the
 * current locale from `query()`, so a reindex that scans `query()` silently leaves
 * mono-language rows out of the search index.
 */
beforeEach(function (): void {
    LocaleHiddenSearchStubModel::$indexed = [];

    config(['app.locale' => 'it']);
    LocaleContext::set('it');

    Schema::create('core_test_locale_hidden_search_stub', function (Blueprint $table): void {
        $table->id();
    });

    Schema::create('core_test_locale_hidden_search_stub_translations', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('locale_hidden_search_stub_model_id');
        $table->string('locale');
    });

    $this->visible = new LocaleHiddenSearchStubModel;
    $this->visible->saveQuietly();
    $this->visible->translations()->create(['locale' => 'it']);

    $this->hidden = new LocaleHiddenSearchStubModel;
    $this->hidden->saveQuietly();
    $this->hidden->translations()->create(['locale' => 'en']);
});

it('has a row that the runtime LocaleScope hides', function (): void {
    expect(LocaleHiddenSearchStubModel::query()->pluck('id')->all())->toBe([$this->visible->getKey()]);
});

it('reindexes rows hidden by LocaleScope on the bulk path', function (): void {
    (new ReindexSearchJob(LocaleHiddenSearchStubModel::class))->handle();

    expect(LocaleHiddenSearchStubModel::$indexed)
        ->toEqualCanonicalizing([$this->visible->getKey(), $this->hidden->getKey()]);
});

it('reindexes rows hidden by LocaleScope on the individual path', function (): void {
    (new ReindexSearchJob(LocaleHiddenSearchStubModel::class, use_bulk: false))->handle();

    expect(LocaleHiddenSearchStubModel::$indexed)
        ->toEqualCanonicalizing([$this->visible->getKey(), $this->hidden->getKey()]);
});
