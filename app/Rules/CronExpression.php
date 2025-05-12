<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Override;

final class CronExpression implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (isset($value)) {
            try {
                new \Cron\CronExpression($value);
            } catch (InvalidArgumentException $ex) {
                $fail($attribute . ' ' . $ex->getMessage());
            }
        }
    }
}
