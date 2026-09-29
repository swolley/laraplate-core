<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Seeding\SeedReconciler;

it('stamps is_internal from the declaring module ownership, not from the seeder', function (): void {
    $definition = new ReflectionMethod(Seeder::class, 'internalSettingsDefinition');

    $owned = $definition->invoke(null, 'Core', [['name' => 'ownership.owned']]);
    $third_party = $definition->invoke(null, 'DefinitelyNotAModule', [['name' => 'ownership.third_party']]);

    expect($owned->rows[0]['is_internal'])->toBeTrue()
        ->and($third_party->rows[0]['is_internal'])->toBeFalse()
        ->and($owned->structural)->toContain('is_internal');
});

it('keeps an operator chosen group across re-seeds', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('is_internal', true)->firstOrFail();
    $seeded_group = $setting->group_name;

    Setting::query()->withoutGlobalScopes()
        ->whereKey($setting->getKey())
        ->update(['group_name' => 'operator_group', 'description' => 'drifted description']);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $fresh = $setting->fresh();

    expect($fresh->group_name)->toBe('operator_group')
        ->and($fresh->group_name)->not->toBe($seeded_group)
        ->and($fresh->description)->not->toBe('drifted description');
});

function seedingDefinitionRow(array $overrides = []): array
{
    return [
        'name' => 'seed.managed',
        'value' => 'a',
        'encrypted' => false,
        'choices' => ['a'],
        'type' => SettingTypeEnum::String,
        'group_name' => 'test',
        'description' => 'Managed',
        ...$overrides,
    ];
}

it('realigns the action columns and defaults them on rows that declare none', function (): void {
    $definition = (new ReflectionMethod(Seeder::class, 'internalSettingsDefinition'))
        ->invoke(null, 'Core', [['name' => 'seed.plain']]);

    expect($definition->structural)->toContain('action_command', 'action_queued', 'choices')
        ->and($definition->rows[0]['action_command'])->toBeNull()
        ->and($definition->rows[0]['action_queued'])->toBeFalse();
});

it('leaves choices out of the realigned columns for command-managed settings', function (): void {
    $definition = (new ReflectionMethod(Seeder::class, 'commandManagedChoicesSettingsDefinition'))
        ->invoke(null, 'Core', [seedingDefinitionRow()]);

    expect($definition->structural)->toContain('action_command', 'action_queued')
        ->and($definition->structural)->not->toContain('choices');
});

it('realigns the action but keeps command-written choices on re-seed', function (): void {
    $managed = new ReflectionMethod(Seeder::class, 'commandManagedChoicesSettingsDefinition');
    $reconciler = app(SeedReconciler::class);

    $reconciler->reconcile($managed->invoke(null, 'Core', [seedingDefinitionRow(['action_command' => 'probe:one {name}'])]));

    Setting::query()->withoutGlobalScopes()->where('name', 'seed.managed')
        ->update(['choices' => json_encode(['a', 'b'])]);

    $reconciler->reconcile($managed->invoke(null, 'Core', [
        seedingDefinitionRow(['action_command' => 'probe:two {name}', 'action_queued' => true]),
    ]));

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'seed.managed')->sole();

    expect($setting->choices)->toBe(['a', 'b'])
        ->and($setting->action_command)->toBe('probe:two {name}')
        ->and($setting->action_queued)->toBeTrue();
});

it('still realigns the choices of ordinary settings', function (): void {
    $internal = new ReflectionMethod(Seeder::class, 'internalSettingsDefinition');
    $reconciler = app(SeedReconciler::class);

    $reconciler->reconcile($internal->invoke(null, 'Core', [seedingDefinitionRow(['name' => 'seed.ordinary'])]));

    Setting::query()->withoutGlobalScopes()->where('name', 'seed.ordinary')
        ->update(['choices' => json_encode(['a', 'b'])]);

    $reconciler->reconcile($internal->invoke(null, 'Core', [seedingDefinitionRow(['name' => 'seed.ordinary'])]));

    expect(Setting::query()->withoutGlobalScopes()->where('name', 'seed.ordinary')->sole()->choices)->toBe(['a']);
});
