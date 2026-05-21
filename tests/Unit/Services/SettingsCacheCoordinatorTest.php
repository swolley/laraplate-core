<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;

beforeEach(function (): void {
    app(SettingsCacheCoordinator::class)->flushAll();
});

it('flushes the settings resolver persistent cache', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'coordinator_flush_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver = app(PerModelSettingResolver::class);

    expect($resolver->boolean('coordinator_flush_test', false))->toBeTrue();

    Setting::query()->where('name', 'coordinator_flush_test')->update(['value' => false]);

    expect($resolver->boolean('coordinator_flush_test', true))->toBeTrue();

    app(SettingsCacheCoordinator::class)->flushAll();

    expect($resolver->boolean('coordinator_flush_test', true))->toBeFalse();
});

it('runs registered invalidators on flush all', function (): void {
    $called = false;

    app(SettingsCacheCoordinator::class)->registerInvalidator(static function () use (&$called): void {
        $called = true;
    });

    app(SettingsCacheCoordinator::class)->flushAll();

    expect($called)->toBeTrue();
});

it('invalidates settings on model save via observer', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'observer_flush_test',
        'value' => 10,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver = app(PerModelSettingResolver::class);

    expect($resolver->int('observer_flush_test', 0))->toBe(10);

    $setting->setForcedApprovalUpdate(true);
    $setting->value = 20;
    $setting->save();

    expect($resolver->int('observer_flush_test', 0))->toBe(20)
        ->and(Cache::has(PerModelSettingResolver::cacheKey()))->toBeTrue();
});
