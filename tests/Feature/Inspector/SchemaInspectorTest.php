<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Inspector\Inspect;
use Modules\Core\Inspector\SchemaInspector;

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

it('keeps stale metadata until clearTable is called after schema change', function (): void {
    $table = 'inspect_drift_' . bin2hex(random_bytes(4));
    Schema::create($table, function (Illuminate\Database\Schema\Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('name');
    });

    try {
        $inspector = SchemaInspector::getInstance();
        $before = $inspector->columns($table)->pluck('name')->all();
        expect($before)->toContain('name')->not->toContain('status');

        Schema::table($table, function (Illuminate\Database\Schema\Blueprint $blueprint): void {
            $blueprint->string('status')->nullable();
        });

        $stale = $inspector->columns($table)->pluck('name')->all();
        expect($stale)->not->toContain('status');

        $inspector->clearTable($table);
        $after = $inspector->columns($table)->pluck('name')->all();
        expect($after)->toContain('status');
    } finally {
        Schema::dropIfExists($table);
    }
});

it('keeps inspection cache isolated by connection name', function (): void {
    $secondary_database = tempnam(sys_get_temp_dir(), 'inspector_secondary_');

    config()->set('database.connections.inspector_secondary', [
        'driver' => 'sqlite',
        'database' => $secondary_database,
        'prefix' => '',
    ]);

    DB::purge('inspector_secondary');

    Schema::connection('inspector_secondary')->create('users', function (Illuminate\Database\Schema\Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('username_secondary');
    });

    try {
        $inspector = SchemaInspector::getInstance();
        $default_columns = $inspector->columns('users')->pluck('name')->all();
        $secondary_columns = $inspector->columns('users', 'inspector_secondary')->pluck('name')->all();

        expect($default_columns)->toContain('email')
            ->and($secondary_columns)->toContain('username_secondary')
            ->and($secondary_columns)->not->toContain('email');
    } finally {
        Schema::connection('inspector_secondary')->dropIfExists('users');
        DB::purge('inspector_secondary');

        if (is_string($secondary_database) && file_exists($secondary_database)) {
            @unlink($secondary_database);
        }
    }
});

it('throws for unknown connection during inspection', function (): void {
    expect(fn (): ?Modules\Core\Inspector\Entities\Table => SchemaInspector::getInstance()->table('users', 'missing_connection_name'))
        ->toThrow(InvalidArgumentException::class);
});
