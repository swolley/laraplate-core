<?php

declare(strict_types=1);

namespace Modules\Core\Logging\Fingerprint;

/**
 * One normalization step in the fingerprint chain. Each rule is dependency-free
 * and idempotent so it can be unit-tested in isolation and reordered safely.
 */
interface Rule
{
    public function apply(string $message): string;
}
