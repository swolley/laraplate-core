<?php

declare(strict_types=1);

use Modules\CMS\Models\Tag;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('uses model property when translation_fallback_enabled is declared', function (): void {
    $tag = new Tag();

    expect($tag->translationFallbackEnabledBySettings())->toBeTrue();
});

it('reads translation fallback from settings when property is not declared', function (): void {
    $model = new FakeTranslatableModel();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'translation_fallback_' . $model->getTable(),
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
