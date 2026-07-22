<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class DirtyActiveVersionSetRootException extends LogicException
{
    public function __construct()
    {
        parent::__construct(
            'A distinct nested root cannot join while the active root has unsaved changes.',
        );
    }
}
