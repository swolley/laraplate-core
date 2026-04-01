<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\SortableStubModel;

uses(LaravelTestCase::class);

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
