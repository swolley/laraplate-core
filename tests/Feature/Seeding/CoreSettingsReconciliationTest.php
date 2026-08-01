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

    // Setting::query()->forceCreate() silently no-ops in this codebase:
    // Setting::requiresApprovalWhen() shadows HasApprovals and cancels every
    // direct save. Use the factory state that bypasses approval capture so
    // the orphan row is actually persisted.
    $orphan = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'version_strategy_gone_table',
        'value' => 'DIFF',
        'encrypted' => false,
        'type' => Modules\Core\Casts\SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Orphan',
    ]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($orphan->getKey()))
        ->not->toBeNull();
});
