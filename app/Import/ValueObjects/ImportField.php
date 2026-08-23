<?php

declare(strict_types=1);

namespace Modules\Core\Import\ValueObjects;

/**
 * One target field an entity importer can receive from a mapped source column.
 * The importer declares the set of these; the UI renders one mapping dropdown per
 * field, and header auto-match compares a source column against `name` and `label`.
 */
final readonly class ImportField
{
    /**
     * @param  list<string>  $aliases  Extra source-header spellings that auto-match this field.
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $required = false,
        public array $aliases = [],
    ) {}

    /**
     * @return array{name: string, label: string, required: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
        ];
    }
}
