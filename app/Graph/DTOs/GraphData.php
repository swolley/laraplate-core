<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphData
{
    /**
     * @param  list<GraphNode>  $nodes
     * @param  list<GraphEdge>  $edges
     */
    public function __construct(
        public string $center,
        public array $nodes,
        public array $edges,
        public GraphMeta $graphMeta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'center' => $this->center,
            'nodes' => array_map(static fn (GraphNode $node): array => $node->toArray(), $this->nodes),
            'edges' => array_map(static fn (GraphEdge $edge): array => $edge->toArray(), $this->edges),
            'graphMeta' => $this->graphMeta->toArray(),
        ];
    }
}
