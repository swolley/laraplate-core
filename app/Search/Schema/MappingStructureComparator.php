<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

/**
 * Compares a model's declared Elasticsearch mapping against the live mapping,
 * field by field, so index drift (for example a field whose type changed from
 * `text` to `object` after a schema change) is detected instead of silently
 * causing `document_parsing_exception` at index time.
 *
 * Only declared fields are checked, and only by mapping type. Extra live fields
 * added by dynamic mapping, and ES-normalised sub-keys (keyword sub-fields,
 * analyzers, `doc_values`), are ignored, so a structurally correct index never
 * reports drift.
 */
final class MappingStructureComparator
{
    /**
     * @param  array<string, mixed>  $expected  Declared `properties` map.
     * @param  array<string, mixed>  $live  Live Elasticsearch `properties` map.
     */
    public static function matches(array $expected, array $live): bool
    {
        return self::diff($expected, $live) === [];
    }

    /**
     * List, in human-readable form, every declared field whose live mapping is
     * missing or has a different type. Field paths are dotted, so a drifted
     * per-locale sub-field reads as `title.en`.
     *
     * @param  array<string, mixed>  $expected  Declared `properties` map.
     * @param  array<string, mixed>  $live  Live Elasticsearch `properties` map.
     * @return list<string>
     */
    public static function diff(array $expected, array $live, string $prefix = ''): array
    {
        $mismatches = [];

        foreach ($expected as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $path = $prefix === '' ? (string) $name : $prefix . '.' . $name;
            $live_definition = $live[$name] ?? null;

            if (! is_array($live_definition)) {
                $mismatches[] = sprintf("field '%s' is declared by the model but missing from the live index", $path);

                continue;
            }

            $expected_type = self::type($definition);
            $live_type = self::type($live_definition);

            if ($expected_type !== $live_type) {
                $mismatches[] = sprintf(
                    "field '%s' is '%s' in the live index but the model declares '%s'",
                    $path,
                    $live_type ?? 'unknown',
                    $expected_type ?? 'unknown',
                );

                continue;
            }

            if ($expected_type === 'object'
                && is_array($definition['properties'] ?? null)
                && is_array($live_definition['properties'] ?? null)) {
                $mismatches = array_merge(
                    $mismatches,
                    self::diff($definition['properties'], $live_definition['properties'], $path),
                );
            }
        }

        return $mismatches;
    }

    /**
     * Resolve a field's mapping type. Elasticsearch omits `type` for object
     * fields and reports only their `properties`, so an entry carrying
     * `properties` and no `type` is treated as an object.
     *
     * @param  array<string, mixed>  $definition
     */
    private static function type(array $definition): ?string
    {
        if (isset($definition['type']) && is_string($definition['type'])) {
            return $definition['type'];
        }

        return isset($definition['properties']) ? 'object' : null;
    }
}
