<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphStats
{
    /**
     * @param  array<string, int>  $nodesByModule
     * @param  array<string, int>  $nodesByEntity
     * @param  array<string, int>  $edgesByRelation
     * @param  array<string, int>  $edgesByType
     */
    public function __construct(
        public int $totalNodes,
        public int $totalEdges,
        public array $nodesByModule,
        public array $nodesByEntity,
        public array $edgesByRelation,
        public array $edgesByType,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalNodes' => $this->totalNodes,
            'totalEdges' => $this->totalEdges,
            'nodesByModule' => $this->nodesByModule,
            'nodesByEntity' => $this->nodesByEntity,
            'edgesByRelation' => $this->edgesByRelation,
            'edgesByType' => $this->edgesByType,
        ];
    }
}
