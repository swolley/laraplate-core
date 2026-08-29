<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use RuntimeException;

final class AggregateRestoreConflictException extends RuntimeException
{
    public function __construct(
        public readonly int $expectedRevision,
        public readonly ?int $currentRevision,
    ) {
        parent::__construct(sprintf(
            'Cannot restore: the aggregate is at revision %s but revision %d was expected.',
            $currentRevision === null ? 'none' : (string) $currentRevision,
            $expectedRevision,
        ));
    }
}
