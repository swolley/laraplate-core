<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

use Override;

/**
 * Keeps the stable SQLSTATE class (and optional driver code) while dropping the
 * volatile human tail ("Table 'shop.widgets' doesn't exist"), so the same class
 * of database error groups together.
 */
final class CollapseSqlState implements Rule
{
    #[Override]
    public function apply(string $message): string
    {
        return (string) preg_replace(
            '/(SQLSTATE\[[0-9A-Za-z]+\])(\s*\[\d+\])?.*$/s',
            '$1$2',
            $message,
        );
    }
}
