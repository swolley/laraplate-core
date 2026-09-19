<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\Stubs\Search\SearchableChildStubModel;
use Modules\Core\Tests\Stubs\Search\SearchableParentStubModel;

beforeEach(function (): void {
    Schema::create('searchable_parent_stubs', function ($table): void {
        $table->id();
    });

    Schema::create('searchable_child_stubs', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id');
    });

    foreach (range(1, 3) as $i) {
        $parent = SearchableParentStubModel::query()->create();
        SearchableChildStubModel::query()->create(['parent_id' => $parent->id]);
        SearchableChildStubModel::query()->create(['parent_id' => $parent->id]);
    }
});

it('eager-loads the toSearchableWith relations in one query for the whole chunk', function (): void {
    $models = SearchableParentStubModel::query()->get();
    expect($models->every(fn ($m): bool => ! $m->relationLoaded('children')))->toBeTrue();

    DB::enableQueryLog();
    $models->first()->makeSearchableUsing($models);
    $child_reads = collect(DB::getQueryLog())
        ->filter(static fn (array $q): bool => str_contains($q['query'], 'searchable_child_stubs'))
        ->count();

    // One query loads the children for all three parents, not one per parent.
    expect($child_reads)->toBe(1)
        ->and($models->every(fn ($m): bool => $m->relationLoaded('children')))->toBeTrue();

    // Reading the relation afterwards issues nothing further.
    DB::flushQueryLog();
    $models->each(fn ($m) => $m->children->count());
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});
