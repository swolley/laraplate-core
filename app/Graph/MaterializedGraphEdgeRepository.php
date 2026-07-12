<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Modules\Core\Models\GraphEdge;

final class MaterializedGraphEdgeRepository
{
    /**
     * @param  list<array<string, mixed>>  $edges
     */
    public function upsertMany(array $edges): void
    {
        foreach ($edges as $edge) {
            $attributes = $this->normalizeEdge($edge);

            GraphEdge::query()->updateOrCreate(
                ['edge_key' => $attributes['edge_key']],
                [...$attributes, 'stale_at' => null],
            );
        }
    }

    /**
     * @return Collection<int, GraphEdge>
     */
    public function outgoingForSource(string $sourceNodeId): Collection
    {
        return GraphEdge::query()
            ->notStale()
            ->where('source_node_id', $sourceNodeId)
            ->orderBy('relation')
            ->orderBy('target_node_id')
            ->get();
    }

    public function markStaleForSource(string $sourceNodeId): int
    {
        return GraphEdge::query()
            ->notStale()
            ->where('source_node_id', $sourceNodeId)
            ->update(['stale_at' => Date::now()]);
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, mixed>
     */
    private function normalizeEdge(array $edge): array
    {
        $sourceNodeId = (string) $edge['source_node_id'];
        $targetNodeId = (string) $edge['target_node_id'];
        $relationPath = (string) $edge['relation_path'];
        $relation = (string) $edge['relation'];
        $type = isset($edge['type']) && is_string($edge['type']) && $edge['type'] !== '' ? $edge['type'] : null;

        return [
            'edge_key' => $this->edgeKey($sourceNodeId, $relationPath, $targetNodeId, $type),
            'source_module' => (string) $edge['source_module'],
            'source_entity' => (string) $edge['source_entity'],
            'source_key' => (string) $edge['source_key'],
            'source_node_id' => $sourceNodeId,
            'target_module' => (string) $edge['target_module'],
            'target_entity' => (string) $edge['target_entity'],
            'target_key' => (string) $edge['target_key'],
            'target_node_id' => $targetNodeId,
            'relation' => $relation,
            'relation_path' => $relationPath,
            'type' => $type,
            'directed' => (bool) ($edge['directed'] ?? true),
            'metadata' => is_array($edge['metadata'] ?? null) ? $edge['metadata'] : null,
        ];
    }

    private function edgeKey(string $sourceNodeId, string $relationPath, string $targetNodeId, ?string $type): string
    {
        return hash('xxh128', $sourceNodeId . '|' . $relationPath . '|' . $targetNodeId . '|' . (string) $type);
    }
}
