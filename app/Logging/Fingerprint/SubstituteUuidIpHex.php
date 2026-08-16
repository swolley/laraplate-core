<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

use Override;

/**
 * Substitutes the always-volatile identifiers — UUIDs, IPv4 addresses and long
 * hex tokens — that would otherwise make every occurrence a distinct group.
 */
final class SubstituteUuidIpHex implements Rule
{
    #[Override]
    public function apply(string $message): string
    {
        $message = (string) preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i',
            '{uuid}',
            $message,
        );

        $message = (string) preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '{ip}', $message);

        return (string) preg_replace('/\b[0-9a-f]{32,}\b/i', '{hex}', $message);
    }
}
