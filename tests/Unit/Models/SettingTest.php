<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('creates setting via factory with default attributes', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'test_setting',
        'type' => SettingTypeEnum::STRING,
        'group_name' => 'base',
        'encrypted' => false,
    ]);

    expect($setting->exists)->toBeTrue()
        ->and($setting->id)->not->toBeNull()
        ->and($setting->name)->toBe('test_setting')
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
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['name' => 'foo']);

    $method = new ReflectionMethod(Setting::class, 'requiresApprovalWhen');

    $result = $method->invoke($setting, ['name' => 'bar']);
    expect($result)->toBeTrue();
});

it('requiresApprovalWhen returns false when only description is modified', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['name' => 'foo']);

    $method = new ReflectionMethod(Setting::class, 'requiresApprovalWhen');

    $result = $method->invoke($setting, ['description' => 'New description']);
    expect($result)->toBeFalse();
});

it('getRules create rule contains unique constraint closure', function (): void {
    $setting = new Setting;
    $rules = $setting->getRules();

    $create_name_rules = $rules['create']['name'];
    $has_unique = false;

    foreach ($create_name_rules as $rule) {
        if ($rule instanceof Illuminate\Validation\Rules\Unique) {
            $has_unique = true;
        }
    }

    expect($has_unique)->toBeTrue();
});

it('getRules update rule contains unique constraint with ignore', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $rules = $setting->getRules();

    $update_name_rules = $rules['update']['name'];
    $has_unique = false;

    foreach ($update_name_rules as $rule) {
        if ($rule instanceof Illuminate\Validation\Rules\Unique) {
            $has_unique = true;
        }
    }

    expect($has_unique)->toBeTrue();
});

it('casts returns expected keys', function (): void {
    $setting = new Setting;
    $casts = (new ReflectionMethod(Setting::class, 'casts'))->invoke($setting);

    expect($casts)->toHaveKey('value')
        ->and($casts)->toHaveKey('encrypted')
        ->and($casts)->toHaveKey('choices')
        ->and($casts)->toHaveKey('type')
        ->and($casts['type'])->toBe(SettingTypeEnum::class);
});

it('newFactory returns SettingFactory instance', function (): void {
    $method = new ReflectionMethod(Setting::class, 'newFactory');
    $factory = $method->invoke(null);

    expect($factory)->toBeInstanceOf(Modules\Core\Database\Factories\SettingFactory::class);
});
