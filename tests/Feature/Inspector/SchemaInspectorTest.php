<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Inspector\Inspect;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    SchemaInspector::reset();
});

it('returns the same instance from getInstance', function (): void {
    $first = SchemaInspector::getInstance();
    $second = SchemaInspector::getInstance();

    expect($first)->toBe($second);
});

it('memoizes table in memory so same table is returned by reference', function (): void {
    $inspector = SchemaInspector::getInstance();

    $first = $inspector->table('users');
    $second = $inspector->table('users');

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($first)->toBe($second);
});

it('returns columns from memoized table', function (): void {
    $inspector = SchemaInspector::getInstance();

    $columns = $inspector->columns('users');

    expect($columns)->not->toBeEmpty();
});

it('returns indexes from memoized table', function (): void {
    $inspector = SchemaInspector::getInstance();

    $indexes = $inspector->indexes('users');

    expect($indexes)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('clearTable removes table from in-memory cache', function (): void {
    $inspector = SchemaInspector::getInstance();
    $inspector->table('users');

    $inspector->clearTable('users');

    $reflection = new ReflectionClass($inspector);
    $tablesProp = $reflection->getProperty('tables');
    $tables = $tablesProp->getValue($inspector);
    $key = Inspect::keyName('users', null);

    expect($tables)->not->toHaveKey($key);
});

it('clearAll removes all tables from in-memory cache', function (): void {
    $inspector = SchemaInspector::getInstance();
    $inspector->table('users');

    $inspector->clearAll();

    $reflection = new ReflectionClass($inspector);
    $tablesProp = $reflection->getProperty('tables');
    $tables = $tablesProp->getValue($inspector);

    expect($tables)->toBeEmpty();
});

it('reset clears the singleton instance', function (): void {
    $first = SchemaInspector::getInstance();
    SchemaInspector::reset();
    $second = SchemaInspector::getInstance();

    expect($first)->not->toBe($second);
});

it('hasTable returns true for existing table', function (): void {
    expect(SchemaInspector::getInstance()->hasTable('users'))->toBeTrue();
});

it('hasTable returns false for non-existing table', function (): void {
    expect(SchemaInspector::getInstance()->hasTable('nonexistent_table_xyz'))->toBeFalse();
});

it('hasColumn returns true for existing column on table', function (): void {
    expect(SchemaInspector::getInstance()->hasColumn('email', 'users'))->toBeTrue();
});

it('hasColumn returns false for non-existing column', function (): void {
    expect(SchemaInspector::getInstance()->hasColumn('nonexistent_column', 'users'))->toBeFalse();
});

it('index returns null for unknown index name', function (): void {
    $index = SchemaInspector::getInstance()->index('nonexistent_index_name', 'users');

    expect($index)->toBeNull();
});

it('foreignKey returns null for unknown foreign key name', function (): void {
    $foreign_key = SchemaInspector::getInstance()->foreignKey('nonexistent_fk_name', 'users');

    expect($foreign_key)->toBeNull();
});

it('index resolves an existing index when present on table', function (): void {
    $inspector = SchemaInspector::getInstance();
    $indexes = $inspector->indexes('users');

    if ($indexes->isEmpty()) {
        test()->markTestSkipped('No indexes available on users table in current test database.');
    }

    $first_index = $indexes->first();
    $resolved_index = $inspector->index($first_index->name, 'users');

    expect($resolved_index)->toBeInstanceOf(Modules\Core\Inspector\Entities\Index::class)
        ->and($resolved_index->name)->toBe($first_index->name);
});
