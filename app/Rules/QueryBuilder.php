<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\RelationFilter;
use Override;

final class QueryBuilder implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Accepts the decoded array shape, the JSON string persisted by
     * {@see \Modules\Core\Casts\FiltersGroupCast} and the value objects
     * themselves, which are valid by construction.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof FiltersGroup || $value instanceof Filter || $value instanceof RelationFilter) {
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                $fail($attribute . " doesn't have a correct format");

                return;
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            $fail($attribute . " doesn't have a correct format");

            return;
        }

        if (! Arr::isList($value)) {
            $this->validateAssociative($attribute, $value, $fail);
        } else {
            foreach ($value as $idx => $filter) {
                $this->validate(sprintf('%s.%s', $attribute, $idx), $filter, $fail);
            }
        }
    }

    private function validateAssociative(string $attribute, array $value, Closure $fail): void
    {
        if (! array_key_exists('property', $value) && ! array_key_exists('filters', $value)) {
            $fail($attribute . " doesn't have a correct format");

            return;
        }

        if (array_key_exists('relation', $value)) {
            $this->validateRelation($attribute, $value, $fail);

            return;
        }

        if (array_key_exists('property', $value)) {
            if (! array_key_exists('operator', $value)) {
                $fail($attribute . ' "operator" is required');
            }

            if (! array_key_exists('value', $value)) {
                $fail($attribute . ' "value" is required');
            }
        }

        if (array_key_exists('filters', $value)) {
            if (! Arr::isList($value['filters'])) {
                $fail($attribute . " filters doesn't have a correct format");

                return;
            }

            $this->validate($attribute . '.filters', $value['filters'], $fail);
        }
    }

    /**
     * A relation filter names the relation and nests a filters group (not a bare list) evaluated on the
     * related record; the morph types, when given, are a non-empty list of class names.
     *
     * @param  array<string, mixed>  $value
     */
    private function validateRelation(string $attribute, array $value, Closure $fail): void
    {
        if (! is_string($value['relation']) || $value['relation'] === '') {
            $fail($attribute . ' "relation" must be a relation name');
        }

        if (array_key_exists('morph_types', $value)) {
            $types = $value['morph_types'];

            if (! is_array($types) || $types === [] || ! Arr::isList($types) || array_filter($types, static fn (mixed $type): bool => ! is_string($type) || $type === '') !== []) {
                $fail($attribute . ' "morph_types" must be a non-empty list of class names');
            }
        }

        if (! is_array($value['filters'] ?? null) || Arr::isList($value['filters'])) {
            $fail($attribute . ' "filters" must be a filters group');

            return;
        }

        $this->validate($attribute . '.filters', $value['filters'], $fail);
    }
}
