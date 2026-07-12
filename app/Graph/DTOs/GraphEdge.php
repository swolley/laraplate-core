<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphEdge
{
    public function __construct(
        public string $id,
        public string $source,
        public string $target,
        public string $relation,
        public ?string $type = null,
        public bool $directed = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'relation' => $this->relation,
            'type' => $this->type,
            'directed' => $this->directed,
        ];
    }
}
