<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedDefinition;

it('builds a definition fluently', function (): void {
    $definition = SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name'])
        ->initial(['value'])
        ->ownedBy('CMS')
        ->rows([['name' => 'a', 'type' => 'string', 'group_name' => 'base', 'value' => 1]]);

    expect($definition->modelClass)->toBe(Setting::class)
        ->and($definition->identityColumn())->toBe('name')
        ->and($definition->structural)->toBe(['type', 'group_name'])
        ->and($definition->initial)->toBe(['value'])
        ->and($definition->module)->toBe('CMS')
        ->and($definition->rows)->toHaveCount(1);
});

it('rejects composite identities, which the reconciler does not support', function (): void {
    SeedDefinition::for(Setting::class)
        ->identity(['name', 'group_name'])
        ->identityColumn();
})->throws(LogicException::class, 'single-column identity');

it('rejects a row missing an identity value', function (): void {
    SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->rows([['type' => 'string']]);
})->throws(InvalidArgumentException::class, 'name');

it('normalizes empty strings to null, replacing the SettingObserver saving hook', function (): void {
    $definition = SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->initial(['value'])
        ->rows([['name' => 'empty_probe', 'value' => '']]);

    expect($definition->rows[0]['value'])->toBeNull();
});
