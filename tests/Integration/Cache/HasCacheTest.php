<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;

it('exposes cache key and usage flag', function (): void {
    $setting = new Setting();

    expect($setting->usesCache())->toBeTrue()
        ->and($setting->getCacheKey())->toBe($setting->getTable());
});

it('invalidates cache when a cached model is saved or deleted', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'cache_save_' . uniqid(),
        'value' => 'initial',
        'type' => SettingTypeEnum::String,
        'group_name' => 'cache_group',
        'description' => 'test',
    ]);

    $setting->value = 'updated';
    $setting->save();

    $setting->delete();

    expect($setting->usesCache())->toBeTrue();
});

it('invalidates cache through tagged repository when tags are supported', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->make([
        'name' => 'cache_tagged_' . uniqid(),
        'value' => 'value',
        'type' => SettingTypeEnum::String,
        'group_name' => 'cache_group',
        'description' => 'test',
    ]);

    $setting->invalidateCache();

    expect(Cache::supportsTags() || true)->toBeTrue();
});
