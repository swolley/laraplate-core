<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('resolves values through group cache and typed accessors', function (): void {
    $int_name = 'resolver_int_test';
    $bool_name = 'resolver_bool_test';

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $int_name,
        'value' => 42,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'resolver_group',
        'description' => 'test',
    ]);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $bool_name,
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'resolver_group',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    $resolver = app(PerModelSettingResolver::class);

    expect($resolver->collection()->count())->toBeGreaterThan(0)
        ->and($resolver->value('unknown_setting_name', 'fallback'))->toBe('fallback')
        ->and($resolver->int($int_name, 0))->toBe(42)
        ->and($resolver->boolean($bool_name, false))->toBeTrue();
});

it('returns typed defaults for unknown settings', function (): void {
    $resolver = app(PerModelSettingResolver::class);

    expect($resolver->int('missing_int_setting', 7))->toBe(7)
        ->and($resolver->float('missing_float_setting', 1.5))->toBe(1.5)
        ->and($resolver->string('missing_string_setting', 'default'))->toBe('default')
        ->and($resolver->boolean('missing_bool_setting', true))->toBeTrue();
});

it('flushes group caches and name index while keeping database values', function (): void {
    $group = 'flush_group_' . uniqid();
    $name = 'flush_setting_' . uniqid();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => $name,
        'value' => 'stored',
        'type' => SettingTypeEnum::String,
        'group_name' => $group,
        'description' => 'test',
    ]);

    $resolver = app(PerModelSettingResolver::class);
    expect($resolver->string($name, 'missing'))->toBe('stored');

    $resolver->flushGroup($group);
    $resolver->flushGroups([$group, $group, '']);
    $resolver->flushNameIndex();
    $resolver->flush();

    expect($resolver->string($name, 'missing'))->toBe('stored');
});
