<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use RuntimeException;

/**
 * No complete snapshot of the record exists at or before a revision, so its state there cannot be rebuilt (C08).
 */
final class MissingVersionAnchorException extends RuntimeException
{
    public function __construct(public readonly int|string $versionId)
    {
        parent::__construct(sprintf(
            'No complete snapshot of the record exists at or before version %s: its state cannot be rebuilt.',
            $versionId,
        ));
    }
}
