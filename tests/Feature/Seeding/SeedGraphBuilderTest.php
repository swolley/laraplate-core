<?php

declare(strict_types=1);

use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\CMS\Database\Seeders\CMSDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Database\Seeders\PermissionRefreshSeeder;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ItalianTaxCodesSeeder;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;

/**
 * @return array<class-string, int>
 */
function seederPositions(): array
{
    $sorted = app(SeedGraphBuilder::class)->build();

    return array_flip(array_map(
        static fn (SeedNode $node): string => $node->seederClass,
        $sorted,
    ));
}

/**
 * @return list<SeedNode>
 */
function seedNodes(): array
{
    return app(SeedGraphBuilder::class)->build();
}

function seedNodeFor(string $seederClass): SeedNode
{
    $nodes = collect(seedNodes());

    /** @var SeedNode $node */
    $node = $nodes->firstOrFail(fn (SeedNode $n): bool => $n->seederClass === $seederClass);

    return $node;
}

it('orders MES after ERP, which module priority alone got wrong', function (): void {
    $positions = seederPositions();

    expect($positions[MESDatabaseSeeder::class])
        ->toBeGreaterThan($positions[ERPDatabaseSeeder::class]);
});

it('orders Core before every other module seeder', function (): void {
    $positions = seederPositions();
    $core = $positions[CoreDatabaseSeeder::class];

    expect($positions[ERPDatabaseSeeder::class])->toBeGreaterThan($core)
        ->and($positions[MESDatabaseSeeder::class])->toBeGreaterThan($core);
});

it('wires MES to depend on every ERP seeder via module.json requires', function (): void {
    $mes = seedNodeFor(MESDatabaseSeeder::class);

    expect($mes->dependsOn)
        ->toContain(ERPDatabaseSeeder::class)
        ->toContain(ItalianTaxCodesSeeder::class);
});

it('wires ItalianTaxCodesSeeder to depend on ERPDatabaseSeeder, not on alphabetical tie-break', function (): void {
    // This previously worked only because 'E' sorts before 'I' in the graph's
    // deterministic tie-break — ItalianTaxCodesSeeder::run() looks up the
    // default company created by ERPDatabaseSeeder::ensureDefaultCompany(),
    // and warns-and-returns (no throw) when it is missing, so a dropped edge
    // would silently no-seed instead of failing this graph, hence the direct
    // assertion mirroring the PermissionRefreshSeeder edge below.
    expect(seedNodeFor(ItalianTaxCodesSeeder::class)->dependsOn)
        ->toContain(ERPDatabaseSeeder::class);
});

it('wires every module that requires Core to depend on CoreDatabaseSeeder', function (): void {
    foreach ([AIDatabaseSeeder::class, CMSDatabaseSeeder::class, ERPDatabaseSeeder::class] as $seederClass) {
        expect(seedNodeFor($seederClass)->dependsOn)->toContain(CoreDatabaseSeeder::class);
    }
});

it('makes permission:refresh a declared graph node that CoreDatabaseSeeder depends on', function (): void {
    // CoreDatabaseSeeder::defaultRoles() assigns permissions that only exist once
    // permission:refresh has run, so this is the one intra-module edge Core declares.
    expect(seedNodeFor(CoreDatabaseSeeder::class)->dependsOn)->toBe([PermissionRefreshSeeder::class]);
});

it('propagates the permission:refresh node to every module that requires Core, so ERP can rely on it', function (): void {
    // ERPDatabaseSeeder::ensureDomainPermissions() is the obvious consumer, but it gets this
    // edge for free via module.json "requires": ["Core"] — no explicit dependsOn() needed there.
    foreach ([AIDatabaseSeeder::class, CMSDatabaseSeeder::class, ERPDatabaseSeeder::class] as $seederClass) {
        expect(seedNodeFor($seederClass)->dependsOn)->toContain(PermissionRefreshSeeder::class);
    }
});

it('excludes Dev seeders from the production graph', function (): void {
    $classes = array_keys(seederPositions());

    foreach ($classes as $class) {
        expect(class_basename($class))->not->toStartWith('Dev');
    }
});
