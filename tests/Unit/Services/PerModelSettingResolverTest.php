<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
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

it('resolves typed values', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'typed_pagination_test',
        'value' => 15,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect($resolver->int('typed_pagination_test', 25))->toBe(15)
        ->and($resolver->value('typed_pagination_test', 0))->toBe(15);
});

it('filters settings by group name', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'erp_group_test_flag',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'erp',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect($resolver->group('erp')->has('erp_group_test_flag'))->toBeTrue()
        ->and($resolver->group('base')->has('erp_group_test_flag'))->toBeFalse();
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

it('stores each settings group under a group-specific cache key', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translation_group_cache_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse();

    $group = $resolver->group('translations');

    expect($group->has('translation_group_cache_test'))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeTrue();
});

it('resolves name based reads through the settings name index', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'indexed_setting_test',
        'value' => 'from-index',
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'test',
    ]);

    $resolver->flush();

    expect(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeFalse()
        ->and($resolver->string('indexed_setting_test', 'fallback'))->toBe('from-index')
        ->and(Cache::has(PerModelSettingResolver::nameIndexCacheKey()))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('base')))->toBeTrue();
});

it('flushes one group without clearing another loaded group', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translations_group_flush_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'erp_group_flush_test',
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'erp',
        'description' => 'test',
    ]);

    $resolver->flush();
    $resolver->group('translations');
    $resolver->group('erp');

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeTrue()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue();

    $resolver->flushGroup('translations');

    expect(Cache::has(PerModelSettingResolver::groupCacheKey('translations')))->toBeFalse()
        ->and(Cache::has(PerModelSettingResolver::groupCacheKey('erp')))->toBeTrue();
});
