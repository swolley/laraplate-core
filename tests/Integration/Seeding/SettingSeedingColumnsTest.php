<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;

it('persists the owning module and the seeded baseline', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'seeding_columns_probe',
        'value' => 20,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'Probe',
        'module' => 'CMS',
        'seeded_value' => 20,
    ]);

    $fresh = Setting::query()->withoutGlobalScopes()->find($setting->getKey());

    expect($fresh->module)->toBe('CMS')
        ->and($fresh->seeded_value)->toBe(20);
});

it('leaves both columns null for hand-created settings', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'hand_created_probe',
        'value' => 'x',
        'encrypted' => false,
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Probe',
    ]);

    $fresh = Setting::query()->withoutGlobalScopes()->find($setting->getKey());

    expect($fresh->module)->toBeNull()
        ->and($fresh->seeded_value)->toBeNull();
});
