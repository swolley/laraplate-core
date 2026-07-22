<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class VersionSetOptionsMismatchException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Nested version set options must match the active version set options.');
    }
}
