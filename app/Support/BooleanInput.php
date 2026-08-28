<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Schema-aware coercion of request values into real booleans.
 *
 * Laravel's `boolean` validation rule rejects `"true"` / `"yes"` / `"no"`. A global
 * middleware cannot fix that safely: it would also turn a string field whose value
 * happens to be `"no"` into `false`. Callers that already know which attributes are
 * boolean (casts / validation rules on the resolved model) use this instead.
 */
final class BooleanInput
{
    /**
     * Coerce a single value when it is a recognised boolean form; otherwise return it
     * unchanged so validation can still reject garbage like `"maybe"`.
     *
     * Accepted: true/false, 1/0, "1"/"0", "true"/"false", "yes"/"no", "on"/"off"
     * (case-insensitive for the string forms).
     */
    public static function coerce(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $value;
    }

    /**
     * Attribute names the model treats as boolean, from Eloquent casts and validation rules.
     *
     * @return list<string>
     */
    public static function attributeNames(Model $model): array
    {
        $names = [];

        foreach ($model->getCasts() as $attribute => $cast) {
            if (self::isBooleanCast($cast)) {
                $names[] = $attribute;
            }
        }

        if (method_exists($model, 'getOperationRules')) {
            /** @var array<string, mixed> $rules */
            $rules = $model->getOperationRules(null);

            foreach ($rules as $attribute => $rule) {
                if (is_string($attribute) && self::rulesContainBoolean($rule)) {
                    $names[] = $attribute;
                }
            }
        }

        return array_values(array_unique($names));
    }

    public static function isBooleanAttribute(Model $model, string $attribute): bool
    {
        $bare = str_contains($attribute, '.') ? Str::afterLast($attribute, '.') : $attribute;

        return in_array($bare, self::attributeNames($model), true);
    }

    private static function isBooleanCast(mixed $cast): bool
    {
        if (! is_string($cast)) {
            return false;
        }

        $cast = strtolower(str_contains($cast, ':') ? strstr($cast, ':', true) ?: $cast : $cast);

        return $cast === 'bool' || $cast === 'boolean';
    }

    private static function rulesContainBoolean(mixed $rules): bool
    {
        foreach (is_array($rules) ? $rules : explode('|', (string) $rules) as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if (strtolower(trim(explode(':', $rule, 2)[0])) === 'boolean') {
                return true;
            }
        }

        return false;
    }
}
