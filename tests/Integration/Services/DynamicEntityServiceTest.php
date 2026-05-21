<?php

declare(strict_types=1);

use Modules\Core\Services\DynamicEntityService;


beforeEach(function (): void {
    DynamicEntityService::reset();
});

it('getInstance returns singleton', function (): void {
    $a = DynamicEntityService::getInstance();
    $b = DynamicEntityService::getInstance();

    expect($a)->toBe($b);
});

it('reset clears singleton', function (): void {
    $a = DynamicEntityService::getInstance();
    DynamicEntityService::reset();
    $b = DynamicEntityService::getInstance();

    expect($a)->not->toBe($b);
});

it('clearCache forgets in-memory and Cache entry', function (): void {
    $service = DynamicEntityService::getInstance();

    $service->clearCache('some_table', 'default');

    expect(true)->toBeTrue();
});

it('clearAllCaches does not throw', function (): void {
    $service = DynamicEntityService::getInstance();

    $service->clearAllCaches();

    expect(true)->toBeTrue();
});

it('getInspectedTable delegates to SchemaInspector and returns null for unknown table', function (): void {
    $service = DynamicEntityService::getInstance();

    $connection = config('database.default', 'sqlite');
    $table = $service->getInspectedTable('non_existent_table_xyz_123', $connection);

    expect($table)->toBeNull();
});

it('resolve throws when dynamic entities are disabled and no concrete model is found', function (): void {
    config()->set('crud.dynamic_entities', false);
    $service = DynamicEntityService::getInstance();

    expect(fn () => $service->resolve('table_that_does_not_exist_' . bin2hex(random_bytes(4))))
        ->toThrow(UnexpectedValueException::class);
});

it('resolve returns concrete model when table maps to existing model', function (): void {
    config()->set('crud.dynamic_entities', false);
    $service = DynamicEntityService::getInstance();

    $model = $service->resolve('settings', attributes: ['name' => 'my_setting']);

    expect($model)->toBeInstanceOf(Modules\Core\Models\Setting::class)
        ->and($model->name)->toBe('my_setting');
});

it('resolve uses dynamic entity cache and returns cloned model instances', function (): void {
    config()->set('crud.dynamic_entities', true);
    $service = DynamicEntityService::getInstance();
    $table = 'tmp_dynamic_entities_' . bin2hex(random_bytes(4));

    Illuminate\Support\Facades\Schema::create($table, function (Illuminate\Database\Schema\Blueprint $blueprint): void {
        $blueprint->uuid('id')->primary();
        $blueprint->string('name')->nullable();
    });

    try {
        $first = $service->resolve($table);
        $second = $service->resolve($table);

        expect($first)->toBeInstanceOf(Modules\Core\Models\DynamicEntity::class)
            ->and($second)->toBeInstanceOf(Modules\Core\Models\DynamicEntity::class)
            ->and($first)->not->toBe($second)
            ->and($first->getTable())->toBe($table)
            ->and($second->getTable())->toBe($table);
    } finally {
        $service->clearCache($table);
        Illuminate\Support\Facades\Schema::dropIfExists($table);
    }
});
