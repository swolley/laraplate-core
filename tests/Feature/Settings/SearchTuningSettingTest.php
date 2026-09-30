<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => CoreDatabaseSeeder::class, '--no-interaction' => true]);
});

it('seeds the adaptive retrieval tuning switch off in the search group', function (): void {
    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'search.adaptive_tuning')->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value)->toBeFalse()
        ->and($setting->type)->toBe(SettingTypeEnum::Boolean)
        ->and($setting->group_name)->toBe('search')
        ->and($setting->module)->toBe('Core');
});

it('exposes the switch as core.search.adaptive_tuning and follows the row when flipped', function (): void {
    $overlay = app(DatabaseConfigOverlay::class);
    $overlay->applyFromDatabase(app(PerModelSettingResolver::class));

    expect(config('core.search.adaptive_tuning'))->toBeFalse();

    Setting::query()->withoutGlobalScopes()->where('name', 'search.adaptive_tuning')->firstOrFail()
        ->forceFill(['value' => true])->save();
    app(SettingsCacheCoordinator::class)->flushAll();
    $overlay->applyFromDatabase(app(PerModelSettingResolver::class));

    expect(config('core.search.adaptive_tuning'))->toBeTrue();
});
