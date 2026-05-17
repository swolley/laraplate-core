<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('loads settings once and reuses them for subsequent lookups', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'ai_moderation_test_table',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'moderation',
        'description' => 'test',
    ]);

    expect($resolver->boolean('ai_moderation_test_table', false))->toBeTrue()
        ->and($resolver->boolean('ai_moderation_test_table', false))->toBeTrue();
});

it('returns default when setting is missing', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    expect($resolver->boolean('missing_setting_key', true))->toBeTrue()
        ->and($resolver->boolean('missing_setting_key', false))->toBeFalse();
});

it('reloads from database after flush', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'auto_translate_test_table',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    expect($resolver->boolean('auto_translate_test_table', true))->toBeFalse();

    Setting::query()->whereKey($setting->id)->update(['value' => true]);
    $resolver->flush();

    expect($resolver->boolean('auto_translate_test_table', false))->toBeTrue();
});

it('resolves settings regardless of group name', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'custom_flag_test_table',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'custom_group',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect($resolver->boolean('custom_flag_test_table', false))->toBeTrue();
});
