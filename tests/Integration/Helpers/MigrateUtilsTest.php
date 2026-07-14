<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

it('timestamps adds created_at and updated_at when missing', function (): void {
    Schema::create('migrate_utils_ts', function (Blueprint $table): void {
        $table->id();
        MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: false, hasLocks: false, hasValidity: false);
    });

    expect(Schema::hasColumn('migrate_utils_ts', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('migrate_utils_ts', 'updated_at'))->toBeTrue();
});

it('dropTimestamps removes created_at and updated_at', function (): void {
    Schema::create('migrate_utils_drop', function (Blueprint $table): void {
        $table->id();
        MigrateUtils::timestamps($table, true, false, false, false);
    });

    Schema::table('migrate_utils_drop', function (Blueprint $table): void {
        MigrateUtils::dropTimestamps($table, true, false, false, false);
    });

    expect(Schema::hasColumn('migrate_utils_drop', 'created_at'))->toBeFalse()
        ->and(Schema::hasColumn('migrate_utils_drop', 'updated_at'))->toBeFalse();
});

it('creates portable prefix indexes and safely degrades specialized indexes on sqlite', function (): void {
    Schema::create('migrate_utils_search', function (Blueprint $table): void {
        $table->id();
        $table->string('slug');
        $table->text('body');
        MigrateUtils::prefixIndex($table, 'slug');
    });

    MigrateUtils::fuzzyIndex('migrate_utils_search', 'slug');
    MigrateUtils::fullTextIndex('migrate_utils_search', 'body');

    expect(Schema::hasIndex('migrate_utils_search', 'migrate_utils_search_slug_prefix_idx'))->toBeTrue();
});

it('rejects unsafe search index identifiers and unsupported oracle sync modes', function (): void {
    expect(fn () => MigrateUtils::fuzzyIndex('unsafe-table', 'name'))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => MigrateUtils::fullTextIndex('safe_table', 'body', oracleSync: 'hourly'))
        ->toThrow(\InvalidArgumentException::class);
});
