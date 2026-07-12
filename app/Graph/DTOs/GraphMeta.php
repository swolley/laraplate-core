<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphMeta
{
    /**
     * @param  list<string>  $requestedRelations
     * @param  list<string>  $truncatedBy
     */
    public function __construct(
        public int $depth,
        public array $requestedRelations,
        public bool $defaultRelationsApplied = false,
        public bool $truncated = false,
        public array $truncatedBy = [],
        public bool $filteredByAcl = false,
        public bool $hasCycles = false,
        public int $deduplicatedNodeCount = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'depth' => $this->depth,
            'requestedRelations' => $this->requestedRelations,
            'defaultRelationsApplied' => $this->defaultRelationsApplied,
            'truncated' => $this->truncated,
            'truncatedBy' => $this->truncatedBy,
            'filteredByAcl' => $this->filteredByAcl,
            'hasCycles' => $this->hasCycles,
            'deduplicatedNodeCount' => $this->deduplicatedNodeCount,
        ];
    }
}
