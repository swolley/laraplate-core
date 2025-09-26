<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public readonly array $indexTypes = [],
        public readonly array $options = [],
        public readonly bool $required = false,
        public readonly mixed $default = null,
    ) {}

    public function hasIndexType(IndexType $indexType): bool
    {
        return in_array($indexType, $this->indexTypes, true);
    }
}
