<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Modules\Core\Seeding\Exceptions\MissingSeedDependencyException;
use Modules\Core\Seeding\Exceptions\SeedGraphCycleException;

final class SeedGraph
{
    /**
     * Order nodes so every dependency precedes its dependents.
     *
     * Ties are broken by module name then class name, so the same set of nodes
     * always produces the same order regardless of discovery order.
     *
     * @param  list<SeedNode>  $nodes
     * @return list<SeedNode>
     */
    public static function sort(array $nodes): array
    {
        $remaining = [];

        foreach ($nodes as $node) {
            $remaining[$node->seederClass] = $node;
        }

        foreach ($remaining as $node) {
            foreach ($node->dependsOn as $dependency) {
                if (! isset($remaining[$dependency])) {
                    throw MissingSeedDependencyException::for($node->seederClass, $dependency);
                }
            }
        }

        $sorted = [];
        $resolved = [];

        while ($remaining !== []) {
            $ready = array_filter(
                $remaining,
                static fn (SeedNode $node): bool => array_all(
                    $node->dependsOn,
                    static fn (string $dependency): bool => isset($resolved[$dependency]),
                ),
            );

            if ($ready === []) {
                throw SeedGraphCycleException::for(array_keys($remaining));
            }

            uasort(
                $ready,
                static fn (SeedNode $a, SeedNode $b): int => [$a->module, $a->seederClass]
                    <=> [$b->module, $b->seederClass],
            );

            $next = (string) array_key_first($ready);
            $sorted[] = $remaining[$next];
            $resolved[$next] = true;
            unset($remaining[$next]);
        }

        return $sorted;
    }
}
