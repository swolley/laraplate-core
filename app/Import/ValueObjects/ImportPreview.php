<?php

declare(strict_types=1);

namespace Modules\Core\Import\ValueObjects;

/**
 * The payload the mapping UI renders: the detected source columns, the first N
 * data rows as a grid, the target fields to map onto, and a suggested mapping from
 * header auto-match the user can accept or override.
 */
final readonly class ImportPreview
{
    /**
     * @param  list<string>  $columns
     * @param  list<array<string, string>>  $rows
     * @param  list<ImportField>  $fields
     * @param  array<string, string|null>  $suggestedMapping  field name => column or null
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public array $fields,
        public array $suggestedMapping,
    ) {}

    /**
     * @return array{columns: list<string>, rows: list<array<string, string>>, fields: list<array{name: string, label: string, required: bool}>, suggested_mapping: array<string, string|null>}
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows,
            'fields' => array_map(static fn (ImportField $field): array => $field->toArray(), $this->fields),
            'suggested_mapping' => $this->suggestedMapping,
        ];
    }
}
