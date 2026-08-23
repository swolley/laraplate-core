<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use LogicException;
use Modules\Core\Import\Enums\OnMissingRelation;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\ValueObjects\ImportRelationField;

/**
 * Resolves a mapped source cell into a list of related-record ids by natural key.
 *
 * The framework owns the mechanics that every relation shares — splitting a
 * multi-value cell, trimming and de-duplicating tokens, and applying the
 * {@see OnMissingRelation} policy — while the calling importer supplies the two
 * domain-specific callbacks: how to look a token up by its natural key, and (only
 * when the policy is {@see OnMissingRelation::Create}) how to create it. This keeps
 * the resolver blind to the target entity while giving every importer one uniform,
 * declarative way to author to-many and cross-entity relations from a file.
 */
final class RelationValueResolver
{
    /**
     * @param  string|null  $raw  The mapped cell value (may be null when unmapped/empty).
     * @param  callable(string): (int|null)  $find  Maps a token to an existing related id, or null.
     * @param  (callable(string): int)|null  $create  Creates the related record for a token and returns its id.
     * @return list<int> The resolved, de-duplicated related ids.
     */
    public function resolve(?string $raw, ImportRelationField $field, callable $find, ?callable $create = null): array
    {
        $ids = [];
        $missing = [];

        foreach ($this->tokens($raw ?? '', $field) as $token) {
            $id = $find($token);

            if ($id !== null) {
                $ids[] = $id;

                continue;
            }

            match ($field->onMissing) {
                OnMissingRelation::Create => $ids[] = ($create ?? throw new LogicException(
                    "Relation [{$field->name}] uses onMissing=create but no create callback was given.",
                ))($token),
                OnMissingRelation::Skip => null,
                OnMissingRelation::Error => $missing[] = $token,
            };
        }

        if ($missing !== []) {
            throw RowImportException::withErrors([
                $field->name => ['Unknown ' . $field->name . ': ' . implode(', ', $missing) . '.'],
            ]);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Split a cell into de-duplicated, trimmed natural-key tokens. A single-value
     * field yields at most one token; a multi-value field splits on its separator.
     *
     * @return list<string>
     */
    private function tokens(string $raw, ImportRelationField $field): array
    {
        $raw = mb_trim($raw);

        if ($raw === '') {
            return [];
        }

        $parts = $field->multiple ? explode($field->separator, $raw) : [$raw];
        $tokens = [];

        foreach ($parts as $part) {
            $part = mb_trim($part);

            if ($part !== '' && ! in_array($part, $tokens, true)) {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }
}
