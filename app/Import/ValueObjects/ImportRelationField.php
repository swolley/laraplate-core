<?php

declare(strict_types=1);

namespace Modules\Core\Import\ValueObjects;

use Modules\Core\Import\Enums\OnMissingRelation;

/**
 * A mappable source column that resolves to one or more related records by their
 * human-readable natural key (a slug, name or code) rather than an internal id.
 *
 * It carries both the UI-facing mapping metadata (so the column gets a dropdown and
 * auto-match like any {@see ImportField}) and the resolution policy an importer
 * hands to {@see \Modules\Core\Import\Support\RelationValueResolver}: whether the
 * cell holds several values, how they are separated, and what to do with a token
 * that matches nothing.
 */
final readonly class ImportRelationField
{
    /**
     * @param  string  $name  The mapped field key the source column fills.
     * @param  string  $label  Human-readable label for the mapping dropdown.
     * @param  string  $relation  The Eloquent relation method on the target model to sync.
     * @param  bool  $multiple  Whether one cell may hold several natural-key tokens.
     * @param  string  $separator  The delimiter that splits a multi-value cell.
     * @param  OnMissingRelation  $onMissing  What to do with an unmatched token.
     * @param  bool  $required  Whether the column must be mapped before the import runs.
     * @param  list<string>  $aliases  Extra source-header spellings that auto-match this field.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $relation,
        public bool $multiple = true,
        public string $separator = ',',
        public OnMissingRelation $onMissing = OnMissingRelation::Error,
        public bool $required = false,
        public array $aliases = [],
    ) {}

    /**
     * The plain mapping field an importer exposes in {@see EntityImporterInterface::fields()}
     * so the relation column is mapped, auto-matched and (optionally) required like any other.
     */
    public function toField(): ImportField
    {
        return new ImportField($this->name, $this->label, $this->required, $this->aliases);
    }
}
