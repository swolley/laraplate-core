<?php

declare(strict_types=1);

use Modules\Core\Seeding\Exceptions\MissingSeedDependencyException;
use Modules\Core\Seeding\Exceptions\SeedGraphCycleException;
use Modules\Core\Seeding\SeedGraph;
use Modules\Core\Seeding\SeedNode;

/**
 * @param  list<class-string>  $dependsOn
 */
function node(string $class, string $module, array $dependsOn = []): SeedNode
{
    return new SeedNode($class, $module, $dependsOn);
}

it('orders a dependency before its dependent', function (): void {
    $sorted = SeedGraph::sort([
        node('MesSeeder', 'MES', ['ErpSeeder']),
        node('ErpSeeder', 'ERP'),
    ]);

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $sorted))
        ->toBe(['ErpSeeder', 'MesSeeder']);
});

it('breaks ties deterministically by module then class', function (): void {
    $sorted = SeedGraph::sort([
        node('ZebraSeeder', 'CMS'),
        node('AlphaSeeder', 'CMS'),
        node('OtherSeeder', 'AI'),
    ]);

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $sorted))
        ->toBe(['OtherSeeder', 'AlphaSeeder', 'ZebraSeeder']);
});

it('produces the same order regardless of input order', function (): void {
    $nodes = [
        node('C', 'Core', ['A']),
        node('A', 'Core'),
        node('B', 'Core', ['A']),
    ];

    $first = SeedGraph::sort($nodes);
    $second = SeedGraph::sort(array_reverse($nodes));

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $first))
        ->toBe(array_map(fn (SeedNode $n): string => $n->seederClass, $second));
});

it('throws on a cycle and names the involved nodes', function (): void {
    SeedGraph::sort([
        node('A', 'Core', ['B']),
        node('B', 'Core', ['A']),
    ]);
})->throws(SeedGraphCycleException::class, 'A');

it('throws when a declared dependency is not in the graph', function (): void {
    SeedGraph::sort([
        node('MesSeeder', 'MES', ['ErpSeeder']),
    ]);
})->throws(MissingSeedDependencyException::class, 'ErpSeeder');
