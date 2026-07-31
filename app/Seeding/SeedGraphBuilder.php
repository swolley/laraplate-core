<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Support\Str;
use Modules\Core\Seeding\Contracts\DeclaresSeedDependencies;
use Nwidart\Modules\Facades\Module;

use function modules;
use function module_path;

final class SeedGraphBuilder
{
    /**
     * Discover production seeders of enabled modules and return them in execution order.
     *
     * @return list<SeedNode>
     */
    public function build(): array
    {
        $by_module = $this->discover();
        $nodes = [];

        foreach ($by_module as $module => $classes) {
            $inherited = $this->inheritedDependencies($module, $by_module);

            foreach ($classes as $class) {
                $declared = is_subclass_of($class, DeclaresSeedDependencies::class)
                    ? $class::dependsOn()
                    : [];

                $nodes[] = new SeedNode(
                    seederClass: $class,
                    module: $module,
                    dependsOn: array_values(array_unique([...$declared, ...$inherited])),
                );
            }
        }

        return SeedGraph::sort($nodes);
    }

    /**
     * Production seeder classes per enabled module, Dev* excluded.
     *
     * @return array<string, list<class-string>>
     */
    private function discover(): array
    {
        $relative_path = config('modules.paths.generator.seeder.path');
        $discovered = [];

        foreach (modules(prioritySort: false) as $module) {
            $path = module_path(
                $module,
                is_string($relative_path) ? $relative_path : 'database/seeders',
            );

            $classes = [];

            foreach (glob("{$path}/*.php") ?: [] as $file) {
                $basename = basename($file, '.php');

                if (Str::startsWith($basename, 'Dev')) {
                    continue;
                }

                $class = "Modules\\{$module}\\Database\\Seeders\\{$basename}";

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }

            if ($classes !== []) {
                $discovered[$module] = $classes;
            }
        }

        return $discovered;
    }

    /**
     * Every seeder of every module this module requires, transitively resolved by the graph.
     *
     * @param  array<string, list<class-string>>  $byModule
     * @return list<class-string>
     */
    private function inheritedDependencies(string $module, array $byModule): array
    {
        $required = Module::find($module)?->get('requires') ?? [];
        $inherited = [];

        foreach ($required as $requirement) {
            foreach ($byModule[$requirement] ?? [] as $class) {
                $inherited[] = $class;
            }
        }

        return $inherited;
    }
}
