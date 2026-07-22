<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class DistinctVersionSetRootInstanceException extends LogicException
{
    public function __construct()
    {
        parent::__construct(
            'A nested version set must reuse the active aggregate root model instance.',
        );
    }
}
