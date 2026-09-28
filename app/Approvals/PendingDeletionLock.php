<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

use RuntimeException;

/**
 * Thrown when a record is saved while its deletion waits for approval.
 */
final class PendingDeletionLock extends RuntimeException
{
    public static function for(string $model, int|string|null $key): self
    {
        return new self(sprintf('%s #%s has a deletion waiting for approval and cannot be changed.', class_basename($model), (string) $key));
    }
}
