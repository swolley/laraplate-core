<?php

declare(strict_types=1);

namespace Modules\Core\Import\ValueObjects;

use Modules\Core\Import\Enums\OnMissingRelation;

/**
 * The relation metadata a mapping field carries to the UI: whether one cell may
 * hold several natural-key tokens, the delimiter that splits them, and what the
 * import does with a token that matches no existing record. It lets the mapping UI
 * render a relation column differently from a plain scalar one (a multi-value hint,
 * the separator, the missing-token policy) without the client knowing the importer.
 */
final readonly class ImportRelationDescriptor
{
    public function __construct(
        public bool $multiple,
        public string $separator,
        public OnMissingRelation $onMissing,
    ) {}

    /**
     * @return array{multiple: bool, separator: string, on_missing: string}
     */
    public function toArray(): array
    {
        return [
            'multiple' => $this->multiple,
            'separator' => $this->separator,
            'on_missing' => $this->onMissing->value,
        ];
    }
}
