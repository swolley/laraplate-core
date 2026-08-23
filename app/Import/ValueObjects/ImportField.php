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
     * @param  ImportRelationDescriptor|null  $relation  Present when the column resolves to related records by natural key.
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $required = false,
        public array $aliases = [],
        public ?ImportRelationDescriptor $relation = null,
    ) {}

    /**
     * @return array{name: string, label: string, required: bool, relation?: array{multiple: bool, separator: string, on_missing: string}}
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
        ];

        if ($this->relation instanceof ImportRelationDescriptor) {
            $data['relation'] = $this->relation->toArray();
        }

        return $data;
    }
}
