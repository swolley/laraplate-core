<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class InvalidRevertedVersionSetException extends LogicException
{
    public static function wrongConnection(int $id, string $expected, string $actual): self
    {
        return new self(
            "Reverted version set [{$id}] belongs to connection [{$actual}], expected [{$expected}].",
        );
    }

    public static function notFound(int $id, string $connection): self
    {
        return new self("Reverted version set [{$id}] was not found on connection [{$connection}].");
    }

    public static function wrongRoot(int $id): self
    {
        return new self("Reverted version set [{$id}] belongs to a different aggregate root.");
    }
}
