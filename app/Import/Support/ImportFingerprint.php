<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use JsonException;

/**
 * Derives the SHA-256 fingerprint stored on a record origin.
 *
 * The fingerprint answers one question on the next run: did the source payload
 * change since the record was last written? It is computed over the mapped
 * payload an importer produced, with keys sorted so that key order in the
 * source never shows up as a change.
 */
final class ImportFingerprint
{
    /**
     * @param  object|array<string, mixed>  $payload
     */
    public static function of(object|array $payload): ?string
    {
        $values = is_object($payload) ? get_object_vars($payload) : $payload;

        self::sortRecursively($values);

        try {
            $encoded = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            // A payload that cannot be encoded (a resource, a closure) leaves the
            // origin without a fingerprint rather than failing the import.
            return null;
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function sortRecursively(array &$values): void
    {
        ksort($values);

        foreach ($values as &$value) {
            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (is_array($value)) {
                self::sortRecursively($value);
            }
        }
    }
}
