<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

/**
 * Produces the stable 16-character fingerprint shared by the in-process resolver
 * and SAO's payload path. The hash parts are
 * `kind + module + class + normalized file + function + normalized message` —
 * the line number is deliberately absent (kept as metadata elsewhere) so a
 * refactor that shifts lines does not fork the group.
 */
final readonly class Fingerprinter
{
    public function __construct(private FingerprintNormalizer $normalizer) {}

    public function hash(
        string $kind,
        string $module,
        string $class,
        string $file,
        string $function,
        string $message,
    ): string {
        $parts = [
            $kind,
            $module,
            $class,
            $file,
            $function,
            $this->normalizer->normalize($message),
        ];

        return mb_substr(hash('sha256', implode("\0", $parts)), 0, 16);
    }
}
