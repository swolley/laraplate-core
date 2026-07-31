<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
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

it('excludes Dev seeders from the production graph', function (): void {
    $classes = array_keys(seederPositions());

    foreach ($classes as $class) {
        expect(class_basename($class))->not->toStartWith('Dev');
    }
});
