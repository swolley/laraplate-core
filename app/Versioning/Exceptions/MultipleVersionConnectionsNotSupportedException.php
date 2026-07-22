<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use RuntimeException;

final class MultipleVersionConnectionsNotSupportedException extends RuntimeException
{
    public static function forConnections(string $active, string $requested): self
    {
        return new self(
            "Version set connection [{$active}] cannot enlist second connection [{$requested}].",
        );
    }
}
