<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\ModelCapabilityScanner;

it('reports HasApprovals without a second filesystem walk', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $tables = array_column($scanned, null, 'table');

    expect($tables)->toHaveKey((new Setting)->getTable())
        ->and($tables[(new Setting)->getTable()]->hasApprovals)->toBeTrue();
});

it('computes the trait set once per model', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $classes = array_column($scanned, 'modelClass');

    expect($classes)->toBe(array_unique($classes));
});
