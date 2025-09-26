<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

class SchemaDefinition
{
    /**
     * @var FieldDefinition[]
     */
    private array $fields = [];

    public function __construct(
        public readonly string $name,
        public readonly array $options = [],
    ) {}

    public function addField(FieldDefinition $field): self
    {
        $this->fields[$field->name] = $field;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?FieldDefinition
    {
        return $this->fields[$name] ?? null;
    }
}
