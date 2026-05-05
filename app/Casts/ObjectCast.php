<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Override;
use stdClass;

final class ObjectCast implements CastsAttributes
{
    /**
     * Decode JSON for attributes that must surface as a PHP object (e.g. Field::options).
     *
     * json_decode maps JSON objects to stdClass, but JSON arrays to array — not stdClass.
     * Empty or list-shaped payloads are often stored as "[]"; this cast normalizes the
     * top-level value to object to match the return type and Field PHPDoc.
     */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): object
    {
        if ($value instanceof stdClass) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        $decoded = json_decode((string) $value);

        if ($decoded === null) {
            return new stdClass();
        }

        if (is_array($decoded)) {
            return (object) $decoded;
        }

        if (is_object($decoded)) {
            return $decoded;
        }

        return new stdClass();
    }

    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode($value);
    }
}
