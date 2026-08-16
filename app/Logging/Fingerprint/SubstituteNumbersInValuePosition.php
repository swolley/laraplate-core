<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

use Override;

/**
 * Substitutes numbers only where they sit in value position — after `=` or `:`,
 * after `#`, or inside quotes. A bare number carrying meaning (an HTTP 404 vs a
 * 500) is left intact, so distinct errors do not merge into one group.
 */
final class SubstituteNumbersInValuePosition implements Rule
{
    #[Override]
    public function apply(string $message): string
    {
        // Assignment / key-value position: "= 500", ": 42".
        $message = (string) preg_replace('/([=:]\s*)\d+(\.\d+)?\b/', '${1}{n}', $message);

        // Anchored position: "#42".
        $message = (string) preg_replace('/(#)\d+\b/', '${1}{n}', $message);

        // Quoted numbers: '42', "42".
        return (string) preg_replace('/([\'"])\d+(\.\d+)?([\'"])/', '${1}{n}${3}', $message);
    }
}
