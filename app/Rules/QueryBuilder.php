<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
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
        if ($value instanceof FiltersGroup || $value instanceof Filter) {
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
}
