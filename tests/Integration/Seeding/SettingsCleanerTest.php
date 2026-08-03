<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\ModuleState;
use Modules\Core\Seeding\ModuleStateResolver;
use Modules\Core\Seeding\SettingsCleaner;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\Core\Tests\Stubs\Seeding\FixedModuleStateResolver;

/**
 * forceCreate() silently no-ops on Setting: requiresApprovalWhen() shadows the
 * HasApprovals version and cancels every direct save. Use the factory state
 * that skips validation and forces the write, as SettingTest.php does.
 */
function seededSetting(string $name, mixed $value, mixed $baseline, ?string $module): Setting
{
    return Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $name,
        'value' => $value,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'Probe',
        'module' => $module,
        'seeded_value' => $baseline,
    ]);
}

function resolverReturning(ModuleState $state): void
{
    app()->instance(ModuleStateResolver::class, new FixedModuleStateResolver($state));
}

it('hard deletes an untouched setting of a disabled module', function (): void {
    $setting = seededSetting('clean_untouched', 5, 5, 'MES');
    resolverReturning(ModuleState::Disabled);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey()))
        ->toBeNull();
});

it('soft deletes a customized setting of a disabled module', function (): void {
    $setting = seededSetting('clean_touched', 99, 5, 'MES');
    resolverReturning(ModuleState::Disabled);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->value)->toBe(99);
});

it('force deletes settings of a module absent from disk', function (): void {
    $setting = seededSetting('clean_absent', 99, 5, 'GHOST');
    resolverReturning(ModuleState::Absent);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey()))
        ->toBeNull();
});

it('never touches a setting missing its seeded baseline', function (): void {
    // module is set but seeded_value is null: only the whereNotNull('seeded_value')
    // clause excludes this row. Pins that clause independently of whereNotNull('module').
    $setting = seededSetting('clean_no_baseline', 42, null, 'GHOST');
    resolverReturning(ModuleState::Absent);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse();
});

it('never touches a setting missing its module stamp', function (): void {
    // seeded_value is set but module is null: only the whereNotNull('module')
    // clause excludes this row. Pins that clause independently of whereNotNull('seeded_value').
    $setting = seededSetting('clean_no_module', 42, 5, null);
    resolverReturning(ModuleState::Absent);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse();
});

it('soft deletes unconditionally even when soft_deletes_core_settings is disabled', function (): void {
    // Setting::delete() routes through Modules\Core\SoftDeletes\SoftDeletes::
    // performDeleteOnModel(), which downgrades to a forceDelete() whenever
    // soft_deletes_core_settings is false — a row in the very table being
    // cleaned. Prove the cleaner's soft-delete branch does not depend on it.
    $flag_name = PerModelSettingResolver::nameFor('soft_deletes', (new Setting)->getTable());

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $flag_name,
        'value' => false,
        'encrypted' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'soft_deletes',
        'description' => 'Probe',
    ]);

    app(SettingsCacheCoordinator::class)->flushAll();

    $setting = seededSetting('clean_soft_bypass', 99, 5, 'MES');
    resolverReturning(ModuleState::Disabled);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->value)->toBe(99);
});

it('leaves settings of enabled modules alone', function (): void {
    $setting = seededSetting('clean_active', 5, 5, 'Core');
    resolverReturning(ModuleState::Enabled);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->find($setting->getKey()))->not->toBeNull();
});
