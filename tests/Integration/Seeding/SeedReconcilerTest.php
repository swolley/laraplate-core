<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedDefinition;
use Modules\Core\Seeding\SeedReconciler;

/**
 * @param  list<array<string,mixed>>  $rows
 */
function settingsDefinition(array $rows): SeedDefinition
{
    return SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name', 'description'])
        ->initial(['value'])
        ->ownedBy('Core')
        ->rows($rows);
}

/**
 * @return array<string,mixed>
 */
function settingRow(string $name, mixed $value, string $description = 'Original'): array
{
    return [
        'name' => $name,
        'value' => $value,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer->value,
        'group_name' => 'base',
        'description' => $description,
    ];
}

it('creates missing rows with module and baseline populated', function (): void {
    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_create', 10)]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_create')->sole();

    expect($outcome->created)->toBe(['recon_create'])
        ->and($setting->module)->toBe('Core')
        ->and($setting->seeded_value)->toBe(10)
        ->and($setting->value)->toBe(10);
});

it('realigns structural fields but never the operator value', function (): void {
    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_drift', 10)]),
    );

    Setting::query()->withoutGlobalScopes()
        ->where('name', 'recon_drift')
        ->update(['value' => json_encode(99)]);

    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_drift', 10, 'Updated description')]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_drift')->sole();

    expect($outcome->realigned)->toBe(['recon_drift'])
        ->and($setting->description)->toBe('Updated description')
        ->and($setting->value)->toBe(99)
        ->and($setting->seeded_value)->toBe(10);
});

it('reports rows as unchanged when nothing structural differs', function (): void {
    $definition = fn (): SeedDefinition => settingsDefinition([settingRow('recon_stable', 5)]);

    app(SeedReconciler::class)->reconcile($definition());
    $outcome = app(SeedReconciler::class)->reconcile($definition());

    expect($outcome->unchanged)->toBe(1)
        ->and($outcome->created)->toBe([])
        ->and($outcome->realigned)->toBe([]);
});

it('restores a soft-deleted row instead of inserting a duplicate', function (): void {
    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_restore', 7)]),
    );

    Setting::query()->withoutGlobalScopes()->where('name', 'recon_restore')->delete();

    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_restore', 7)]),
    );

    $count = Setting::query()->withoutGlobalScopes()->withTrashed()
        ->where('name', 'recon_restore')->count();

    expect($outcome->restored)->toBe(['recon_restore'])
        ->and($count)->toBe(1)
        ->and(Setting::query()->withoutGlobalScopes()->where('name', 'recon_restore')->exists())
        ->toBeTrue();
});

it('backfills the baseline for rows created before the reconciler existed', function (): void {
    // Setting::query()->forceCreate() silently no-ops: requiresApprovalWhen()
    // returns true on every create, and the approval package's saving listener
    // cancels the save. Use the factory state Integration/Models/SettingTest.php
    // uses to bypass approval capture and actually persist the row.
    Setting::factory()->persistedWithoutApprovalCapture()->create(settingRow('recon_legacy', 3));

    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_legacy', 3)]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_legacy')->sole();

    expect($setting->seeded_value)->toBe(3)
        ->and($setting->module)->toBe('Core');
});

it('issues a fixed number of queries regardless of row count', function (): void {
    $small = settingsDefinition([settingRow('q_a', 1)]);
    $large = settingsDefinition(array_map(
        static fn (int $i): array => settingRow("q_bulk_{$i}", $i),
        range(1, 50),
    ));

    DB::enableQueryLog();
    app(SeedReconciler::class)->reconcile($small);
    $small_count = count(DB::getQueryLog());

    DB::flushQueryLog();
    app(SeedReconciler::class)->reconcile($large);
    $large_count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($large_count)->toBe($small_count);
});
