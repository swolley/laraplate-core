<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class PendingVersionSequenceException extends LogicException
{
    public function __construct()
    {
        parent::__construct('A version sequence was allocated but its write was not confirmed.');
    }
}
