<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\ModuleDatabaseActivator;
use Modules\Core\Models\Setting;

beforeEach(function (): void {
    config()->set('modules.cache.enabled', false);
    Cache::forget('modules_db_activator_statuses');
});

it('realigns stale choices with the modules on disk when the active modules change', function (): void {
    $all_modules = ModuleDatabaseActivator::getAllModulesNames();
    $stale_choices = array_values(array_diff($all_modules, ['Core']));

    Setting::query()->where('name', ModuleDatabaseActivator::$RECORD_NAME)->forceDelete();
    Setting::query()->create([
        'name' => ModuleDatabaseActivator::$RECORD_NAME,
        'value' => $stale_choices,
        'choices' => $stale_choices,
        'type' => 'json',
        'group_name' => 'core',
        'description' => 'application modules',
    ]);

    new ModuleDatabaseActivator(app())->setActiveByName('Core', true);

    $setting = Setting::query()->where('name', ModuleDatabaseActivator::$RECORD_NAME)->sole();

    expect($setting->value)->toContain('Core')
        ->and(array_diff($setting->value, $setting->choices))->toBe([])
        ->and($setting->choices)->toEqualCanonicalizing($all_modules);
});

it('refreshes stale choices when seeding the backend modules record', function (): void {
    $all_modules = ModuleDatabaseActivator::getAllModulesNames();

    Setting::query()->where('name', ModuleDatabaseActivator::$RECORD_NAME)->forceDelete();
    Setting::query()->create([
        'name' => ModuleDatabaseActivator::$RECORD_NAME,
        'value' => ['Core'],
        'choices' => ['Core'],
        'type' => 'json',
        'group_name' => 'core',
        'description' => 'application modules',
    ]);

    $setting = ModuleDatabaseActivator::seedBackendModules()->refresh();

    expect($setting->choices)->toEqualCanonicalizing($all_modules);
});

it('creates the missing backend modules record on first read with every required column', function (): void {
    Setting::query()->where('name', ModuleDatabaseActivator::$RECORD_NAME)->forceDelete();

    new ModuleDatabaseActivator(app())->hasStatus('Core', true);

    $setting = Setting::query()->where('name', ModuleDatabaseActivator::$RECORD_NAME)->sole();

    expect($setting->encrypted)->toBeFalse()
        ->and($setting->value)->toEqualCanonicalizing(ModuleDatabaseActivator::getAllModulesNames());
});
