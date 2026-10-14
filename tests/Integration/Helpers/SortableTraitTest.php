<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\Stubs\SortableStubModel;

beforeEach(function (): void {
    Schema::dropIfExists('sortable_stub');

    Schema::create('sortable_stub', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('order')->default(0);
    });
});

it('scope ordered qualifies order column and sorts', function (): void {
    SortableStubModel::query()->insert([
        ['order' => 2],
        ['order' => 1],
    ]);

    $sql = SortableStubModel::query()->ordered('asc')->toSql();

    expect($sql)->toContain('order');
});

it('puts a new record last whatever position it is given', function (): void {
    SortableStubModel::query()->create(['order' => 1]);

    $record = SortableStubModel::query()->create(['order' => 40]);

    expect($record->order)->toBe(2);
});

it('keeps the position a new record is given when asked to', function (): void {
    SortableStubModel::query()->create(['order' => 1]);

    $record = (new SortableStubModel(['order' => 40]))->keepPositionWhenCreating();
    $record->save();

    expect($record->fresh()->order)->toBe(40);
});
