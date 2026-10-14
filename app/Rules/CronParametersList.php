<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Services\CronParameters;
use Override;

final class CronParametersList implements ValidationRule
{
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tokens = is_string($value) ? json_decode($value, true) : $value;

        if (! CronParameters::isValidList($tokens)) {
            $fail("The {$attribute} must be a list of elements: a flag (--all), an option with a value (--limit=10) or an argument. A value must not start with a dash.");
        }
    }
}
