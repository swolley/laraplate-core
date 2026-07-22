<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;
use Modules\Core\Versioning\Data\VersionSetRoot;

final class VersionSetRootMismatchException extends LogicException
{
    public static function between(VersionSetRoot $active, VersionSetRoot $requested): self
    {
        return new self(sprintf(
            'Active version root [%s:%s] does not match requested root [%s:%s].',
            $active->type(),
            $active->id() ?? 'new',
            $requested->type(),
            $requested->id() ?? 'new',
        ));
    }
}
