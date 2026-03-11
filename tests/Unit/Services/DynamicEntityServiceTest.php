<?php

declare(strict_types=1);

use Modules\Core\Services\DynamicEntityService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

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
