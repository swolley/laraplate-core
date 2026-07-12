<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Modules\Core\Graph\DTOs\GraphStats;

final class GraphStatsCalculator
{
    /**
     * @param  array<string, mixed>  $graph
     */
    public function fromGraph(array $graph): GraphStats
    {
        $nodes = array_values(array_filter($graph['nodes'] ?? [], 'is_array'));
        $edges = array_values(array_filter($graph['edges'] ?? [], 'is_array'));

        return new GraphStats(
            totalNodes: count($nodes),
            totalEdges: count($edges),
            nodesByModule: $this->countNodesByModule($nodes),
            nodesByEntity: $this->countNodesByEntity($nodes),
            edgesByRelation: $this->countEdgesByRelation($edges),
            edgesByType: $this->countEdgesByType($edges),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, int>
     */
    private function countNodesByModule(array $nodes): array
    {
        $counts = [];

        foreach ($nodes as $node) {
            $module = $node['module'] ?? null;

            if (! is_string($module) || $module === '') {
                continue;
            }

            $counts[$module] = ($counts[$module] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, int>
     */
    private function countNodesByEntity(array $nodes): array
    {
        $counts = [];

        foreach ($nodes as $node) {
            $module = $node['module'] ?? null;
            $entity = $node['entity'] ?? null;

            if (! is_string($module) || $module === '' || ! is_string($entity) || $entity === '') {
                continue;
            }

            $key = $module . ':' . $entity;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return array<string, int>
     */
    private function countEdgesByRelation(array $edges): array
    {
        $counts = [];

        foreach ($edges as $edge) {
            $relation = $edge['relation'] ?? null;

            if (! is_string($relation) || $relation === '') {
                continue;
            }

            $counts[$relation] = ($counts[$relation] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return array<string, int>
     */
    private function countEdgesByType(array $edges): array
    {
        $counts = [];

        foreach ($edges as $edge) {
            $type = $edge['type'] ?? null;

            if (! is_string($type) || $type === '') {
                continue;
            }

            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
