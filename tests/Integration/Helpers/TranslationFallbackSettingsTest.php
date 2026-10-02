<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;
use Modules\Core\Tests\Fixtures\FallbackDeclaringTranslatableModel;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('uses model property when translation_fallback_enabled is declared', function (): void {
    $model = new FallbackDeclaringTranslatableModel();

    expect($model->translationFallbackEnabledBySettings())->toBeFalse();
});

it('reads translation fallback from settings when property is not declared', function (): void {
    $model = new FakeTranslatableModel();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translations.locale_fallback.' . $model->getTable(),
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    expect($model->translationFallbackEnabledBySettings())->toBeFalse();
});

it('defaults translation fallback to enabled when no setting exists', function (): void {
    $model = new FakeTranslatableModel();

    expect($model->translationFallbackEnabledBySettings())->toBeTrue();
});
