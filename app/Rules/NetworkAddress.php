<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

/**
 * An address, or a network in CIDR notation whose prefix length fits the address family: what an API token
 * can be restricted to ({@see \Modules\Core\Http\Middleware\AuthenticateApiRequest}).
 */
final class NetworkAddress implements ValidationRule
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        [$address, $prefix] = array_pad(explode('/', $value, 2), 2, null);

        $is_v4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        if (! $is_v4 && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        return ctype_digit($prefix) && ($is_v4 ? 32 : 128) >= (int) $prefix;
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('The :attribute must be an address or a network in CIDR notation.');
        }
    }
}
