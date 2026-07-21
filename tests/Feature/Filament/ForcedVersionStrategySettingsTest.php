<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Models\Setting;
use Modules\Core\Services\ForcedVersionStrategySettings;

uses(RefreshDatabase::class);

it('hides historical settings for models with class-forced DIFF strategy', function (): void {
    $resolver = app(ForcedVersionStrategySettings::class);
    expect($resolver->names())->toContain('version_strategy_erp_accounts');

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'version_strategy_erp_accounts', 'value' => 'snapshot',
        'type' => SettingTypeEnum::String, 'group_name' => 'versioning',
        'description' => 'Historical stale setting',
    ]);
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'visible_test_setting', 'value' => 'yes',
        'type' => SettingTypeEnum::String, 'group_name' => 'general',
        'description' => 'Visible setting',
    ]);

    expect(SettingResource::getEloquentQuery()->pluck('name')->all())
        ->toContain('visible_test_setting')
        ->not->toContain('version_strategy_erp_accounts')
        ->and(SettingResource::form(Schema::make()))->toBeInstanceOf(Schema::class);
});
