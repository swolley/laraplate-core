<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedOrchestrator;

it('completes a full seed run and is idempotent', function (): void {
    expect(app(SeedOrchestrator::class)->run())->toBe(0);

    $before = Setting::query()->withoutGlobalScopes()->count();

    expect(app(SeedOrchestrator::class)->run())->toBe(0)
        ->and(Setting::query()->withoutGlobalScopes()->count())->toBe($before);
});

it('attributes every seeded setting to an owning module', function (): void {
    app(SeedOrchestrator::class)->run();

    $unattributed = Setting::query()->withoutGlobalScopes()
        ->whereNull('module')
        ->pluck('name')
        ->all();

    expect($unattributed)->toBe([]);
});
