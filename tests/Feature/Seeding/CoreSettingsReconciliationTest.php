<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;

it('seeds per-model capability settings with the resolver naming', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $name = PerModelSettingResolver::nameFor('version_strategy', (new Setting)->getTable());

    expect(Setting::query()->withoutGlobalScopes()->where('name', $name)->exists())->toBeTrue();
});

it('is idempotent and leaves operator values untouched on a second run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    Setting::query()->withoutGlobalScopes()
        ->where('name', 'pagination')
        ->update(['value' => json_encode(999)]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'pagination')->sole();

    expect($setting->value)->toBe(999);
});

it('no longer force-deletes settings during a run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    // Build a name the reconciliation could genuinely reach: a real,
    // permanently-scanned model's table (Setting itself), for a capability
    // (HasLocks) that model does not have. Setting only uses HasApprovals
    // and HasCache — not HasLocks — so no row in any run's definition set
    // will ever claim this name. This is exactly the shape the old
    // deleteRefuses()/$to_remove_settings pair used to force-delete — it
    // only ever accumulated prefix+table names built from real scanned
    // models missing the corresponding trait. An arbitrary/nonexistent
    // table name would be invisible to both the old and new code alike and
    // would prove nothing.
    //
    // (HasVersions and SoftDeletes are baked into every model via the
    // shared Modules\Core\Overrides\Model base class — see its trait list —
    // so version_strategy_* / soft_deletes_* settings are always claimed
    // and cannot be used to build this scenario.)
    //
    // Setting::query()->forceCreate() silently no-ops in this codebase:
    // Setting::requiresApprovalWhen() shadows HasApprovals and cancels every
    // direct save. Use the factory state that bypasses approval capture so
    // the orphan row is actually persisted.
    $orphan_name = PerModelSettingResolver::nameFor(
        CoreDatabaseSeeder::LOCK_NAME_PREFIX,
        (new Setting)->getTable(),
    );

    $orphan = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $orphan_name,
        'value' => 'DIFF',
        'encrypted' => false,
        'type' => Modules\Core\Casts\SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Orphan',
    ]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $found = Setting::query()->withoutGlobalScopes()->withTrashed()->find($orphan->getKey());

    expect($found)->not->toBeNull()
        ->and($found->trashed())->toBeFalse();
});
