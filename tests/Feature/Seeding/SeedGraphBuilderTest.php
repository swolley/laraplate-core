<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Database\Seeders\PermissionRefreshSeeder;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Nwidart\Modules\Facades\Module;

/**
 * The production seed graph. The rules below hold for whichever modules are installed: each
 * module's seeders follow, and depend on, the seeders of the modules its module.json requires.
 * Edges a module declares for its own seeders are tested in that module.
 *
 * @return list<SeedNode>
 */
function seedNodes(): array
{
    return app(SeedGraphBuilder::class)->build();
}

/**
 * @return array<class-string, int>
 */
function seederPositions(): array
{
    return array_flip(array_map(
        static fn (SeedNode $node): string => $node->seederClass,
        seedNodes(),
    ));
}

function seedNodeFor(string $seederClass): SeedNode
{
    /** @var SeedNode $node */
    return collect(seedNodes())->firstOrFail(fn (SeedNode $n): bool => $n->seederClass === $seederClass);
}

/**
 * @return list<string>
 */
function requiredModulesOf(string $module): array
{
    $required = Module::find($module)?->get('requires') ?? [];

    return is_array($required) ? array_values(array_filter($required, is_string(...))) : [];
}

it('orders and wires every module seeder after every seeder of the modules it requires', function (): void {
    $nodes = seedNodes();
    $positions = seederPositions();

    foreach ($nodes as $node) {
        foreach (requiredModulesOf($node->module) as $required_module) {
            foreach ($nodes as $required) {
                if ($required->module !== $required_module) {
                    continue;
                }

                expect($node->dependsOn)->toContain($required->seederClass)
                    ->and($positions[$node->seederClass])->toBeGreaterThan($positions[$required->seederClass]);
            }
        }
    }
});

it('orders Core before every other module seeder', function (): void {
    $positions = seederPositions();
    $core = $positions[CoreDatabaseSeeder::class];

    foreach (seedNodes() as $node) {
        if ($node->module !== 'Core') {
            expect($positions[$node->seederClass])->toBeGreaterThan($core);
        }
    }
});

it('makes permission:refresh a declared graph node that CoreDatabaseSeeder depends on', function (): void {
    // CoreDatabaseSeeder::defaultRoles() assigns permissions that only exist once
    // permission:refresh has run, so this is the one intra-module edge Core declares.
    expect(seedNodeFor(CoreDatabaseSeeder::class)->dependsOn)->toBe([PermissionRefreshSeeder::class]);
});

it('propagates CoreDatabaseSeeder and the permission:refresh node to every module that requires Core', function (): void {
    // A module seeder that needs its permissions (ERP's ensureDomainPermissions(), for one)
    // gets this edge from module.json "requires": ["Core"], with no dependsOn() of its own.
    foreach (seedNodes() as $node) {
        if (in_array('Core', requiredModulesOf($node->module), true)) {
            expect($node->dependsOn)
                ->toContain(CoreDatabaseSeeder::class)
                ->toContain(PermissionRefreshSeeder::class);
        }
    }
});

it('excludes Dev seeders from the production graph', function (): void {
    foreach (array_keys(seederPositions()) as $class) {
        expect(class_basename($class))->not->toStartWith('Dev');
    }
});
