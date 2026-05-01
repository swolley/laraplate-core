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
