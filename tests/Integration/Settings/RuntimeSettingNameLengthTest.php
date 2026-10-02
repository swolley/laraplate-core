<?php

declare(strict_types=1);

/**
 * Every module seeder that defines runtime settings follows the `{Module}DatabaseSeeder`
 * convention, so this holds for whichever modules are installed.
 */
it('keeps the runtime setting names of every installed module within the Setting model name limit', function (): void {
    $names = [];

    foreach (modules(prioritySort: false) as $module) {
        $seeder = sprintf('Modules\\%s\\Database\\Seeders\\%sDatabaseSeeder', $module, $module);

        if (! class_exists($seeder) || ! method_exists($seeder, 'runtimeSettingDefinitions')) {
            continue;
        }

        foreach ($seeder::runtimeSettingDefinitions() as $definition) {
            $names[] = $definition['name'];
        }
    }

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        expect(mb_strlen($name))->toBeLessThanOrEqual(255, "Setting name [{$name}] is too long.");
    }
});
