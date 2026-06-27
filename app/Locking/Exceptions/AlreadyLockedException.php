<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Exceptions;

use RuntimeException;

final class AlreadyLockedException extends RuntimeException
{
}
