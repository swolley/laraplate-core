<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Support\ForcedModelConfiguration;
use Overtrue\LaravelVersionable\VersionStrategy;

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

it('discovers at least one model with forced soft deletes disabled', function (): void {
    $has_soft_delete_override = collect(ForcedModelConfiguration::cases())
        ->contains(fn (array $case): bool => $case['property'] === 'softDeletesEnabled' && $case['expected'] === false);

    expect($has_soft_delete_override)->toBeTrue();
});

it('discovers at least one model with forced translation fallback enabled', function (): void {
    $has_translation_fallback_override = collect(ForcedModelConfiguration::cases())
        ->contains(fn (array $case): bool => $case['property'] === 'translation_fallback_enabled' && $case['expected'] === true);

    expect($has_translation_fallback_override)->toBeTrue();
});

it('discovers ERP models with forced version strategy DIFF', function (): void {
    $erp_diff_models = collect(ForcedModelConfiguration::cases())
        ->filter(fn (array $case): bool => $case['property'] === 'versionStrategy'
            && $case['expected'] === VersionStrategy::DIFF
            && str_starts_with($case['model'], 'Modules\\ERP\\'));

    expect($erp_diff_models)->not->toBeEmpty();
});

it('applies protected versionStrategy through HasVersions at runtime', function (): void {
    Modules\Core\Models\Concerns\HasVersions::resetVersionStrategyCache();

    $models = [
        Modules\ERP\Models\FiscalPeriod::class,
        Modules\ERP\Models\Account::class,
    ];

    foreach ($models as $model_class) {
        $instance = (new ReflectionClass($model_class))->newInstanceWithoutConstructor();

        expect($instance->getVersionStrategy())->toBe(VersionStrategy::DIFF);
    }
});
