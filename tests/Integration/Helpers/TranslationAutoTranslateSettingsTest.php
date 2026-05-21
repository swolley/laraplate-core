<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('uses model property when auto_translate_enabled is declared', function (): void {
    $model = new class() extends FakeTranslatableModel
    {
        protected bool $auto_translate_enabled = true;
    };

    expect($model->autoTranslateEnabledBySettings())->toBeTrue();
});

it('reads auto translate from settings when property is not declared', function (): void {
    $model = new FakeTranslatableModel();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'auto_translate_' . $model->getTable(),
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'translations',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    expect($model->autoTranslateEnabledBySettings())->toBeTrue();
});

it('defaults auto translate to disabled when no setting exists', function (): void {
    $model = new FakeTranslatableModel();

    expect($model->autoTranslateEnabledBySettings())->toBeFalse();
});
