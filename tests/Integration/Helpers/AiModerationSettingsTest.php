<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Tests\Fixtures\FakeModeratableModel;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('uses model property when ai_moderation_enabled is declared', function (): void {
    $model = new class() extends FakeModeratableModel
    {
        protected bool $ai_moderation_enabled = true;
    };

    expect($model->aiModerationEnabledBySettings())->toBeTrue();
});

it('reads ai moderation from settings when property is not declared', function (): void {
    $model = new FakeModeratableModel();

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'ai_moderation_' . $model->getTable(),
        'value' => true,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'moderation',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    expect($model->aiModerationEnabledBySettings())->toBeTrue();
});

it('defaults ai moderation to disabled when no setting exists', function (): void {
    $model = new FakeModeratableModel();

    expect($model->aiModerationEnabledBySettings())->toBeFalse();
});
