<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

use Override;

/**
 * Removes an embedded stack trace so two occurrences of the same error do not
 * differ only by the frames flattened into the message.
 */
final class StripStackTraces implements Rule
{
    #[Override]
    public function apply(string $message): string
    {
        // Everything from a "Stack trace:" marker onwards is volatile noise.
        $message = (string) preg_replace('/\s*Stack trace:.*$/s', '', $message);

        // Drop any leading numbered stack frames ("#0 ...", "#1 {main}").
        $message = (string) preg_replace('/\s*#\d+\s+.*$/s', '', $message);

        return mb_trim($message);
    }
}
