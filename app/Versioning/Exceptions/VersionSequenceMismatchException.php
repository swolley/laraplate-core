<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class VersionSequenceMismatchException extends LogicException
{
    /**
     * @param  list<int>  $expected
     * @param  list<int>  $persisted
     */
    public function __construct(array $expected, array $persisted)
    {
        parent::__construct(sprintf(
            'Confirmed version sequences [%s] do not match persisted sequences [%s].',
            implode(', ', $expected),
            implode(', ', $persisted),
        ));
    }
}
