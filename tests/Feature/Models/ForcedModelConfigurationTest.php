<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Support\ForcedModelConfiguration;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => CoreDatabaseSeeder::class, '--no-interaction' => true]);
});

it('enforces class-level feature flags without a matching Setting row', function (): void {
    $cases = ForcedModelConfiguration::cases();

    expect($cases)->not->toBeEmpty();

    foreach ($cases as $case) {
        $reflection = new ReflectionClass($case['model']);

        expect(ForcedModelConfiguration::classSourceDeclaresDefault(
            $reflection,
            $case['property'],
            $case['expected'],
        ))->toBeTrue("{$case['model']} must declare {$case['property']} in its class body.");

        $instance = $reflection->newInstanceWithoutConstructor();
        $declared = ForcedModelConfiguration::readDeclaredPropertyValue($instance, $case['property']);
        $expected = ForcedModelConfiguration::normalizeExpected($case['expected']);

        expect($declared)->toEqual($expected);

        expect(
            Setting::query()
                ->where('name', $case['settingName'])
                ->where('group_name', $case['groupName'])
                ->exists(),
        )->toBeFalse("Setting [{$case['settingName']}] must not exist for {$case['model']} when {$case['property']} is forced in code.");
    }
});
