<?php

declare(strict_types=1);

namespace Modules\Core\Graph\DTOs;

readonly class GraphNode
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $module,
        public string $entity,
        public int|string|null $key,
        public ?string $label,
        public array $attributes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'entity' => $this->entity,
            'key' => $this->key,
            'label' => $this->label,
            'attributes' => $this->attributes,
        ];
    }
}
