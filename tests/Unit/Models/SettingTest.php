<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('creates setting via factory with default attributes', function (): void {
    $setting = Setting::factory()->create([
        'name' => 'test_setting',
        'type' => SettingTypeEnum::STRING,
        'group_name' => 'base',
        'encrypted' => false,
    ]);

    expect($setting->name)->toBe('test_setting')
        ->and($setting->type)->toBe(SettingTypeEnum::STRING)
        ->and($setting->group_name)->toBe('base')
        ->and($setting->encrypted)->toBeFalse();
});

it('getRules returns rules with default create and update', function (): void {
    $setting = new Setting;

    $rules = $setting->getRules();

    expect($rules)->toHaveKey('create')
        ->and($rules)->toHaveKey('update')
        ->and($rules)->toHaveKey(Setting::DEFAULT_RULE)
        ->and($rules['create']['name'])->toContain('required')
        ->and($rules['update']['name'])->toContain('sometimes');
});

it('type mutator accepts enum', function (): void {
    $setting = new Setting;
    $setting->type = SettingTypeEnum::BOOLEAN;

    expect($setting->type)->toBe(SettingTypeEnum::BOOLEAN);
});

it('type mutator accepts string and maps to enum', function (): void {
    $setting = new Setting;
    $setting->type = 'integer';

    expect($setting->type)->toBe(SettingTypeEnum::INTEGER);
});

it('type mutator falls back to string for invalid value', function (): void {
    $setting = new Setting;
    $setting->type = 'invalid_type';

    expect($setting->type)->toBe(SettingTypeEnum::STRING);
});

it('requiresApprovalWhen returns true when fillable except description is modified', function (): void {
    $setting = Setting::factory()->create(['name' => 'foo']);

    $method = new ReflectionMethod(Setting::class, 'requiresApprovalWhen');
    $method->setAccessible(true);

    $result = $method->invoke($setting, ['name' => 'bar']);
    expect($result)->toBeTrue();
});

it('requiresApprovalWhen returns false when only description is modified', function (): void {
    $setting = Setting::factory()->create(['name' => 'foo']);

    $method = new ReflectionMethod(Setting::class, 'requiresApprovalWhen');
    $method->setAccessible(true);

    $result = $method->invoke($setting, ['description' => 'New description']);
    expect($result)->toBeFalse();
});
