<?php

declare(strict_types=1);

use Modules\AI\Database\Seeders\AIDatabaseSeeder;
use Modules\CMS\Database\Seeders\CMSDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
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

it('wires every module that requires Core to depend on CoreDatabaseSeeder', function (): void {
    foreach ([AIDatabaseSeeder::class, CMSDatabaseSeeder::class, ERPDatabaseSeeder::class] as $seederClass) {
        expect(seedNodeFor($seederClass)->dependsOn)->toContain(CoreDatabaseSeeder::class);
    }
});

it('leaves Core, which requires nothing, with an empty dependsOn', function (): void {
    expect(seedNodeFor(CoreDatabaseSeeder::class)->dependsOn)->toBe([]);
});

it('excludes Dev seeders from the production graph', function (): void {
    $classes = array_keys(seederPositions());

    foreach ($classes as $class) {
        expect(class_basename($class))->not->toStartWith('Dev');
    }
});
