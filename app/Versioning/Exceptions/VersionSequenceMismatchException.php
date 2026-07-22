<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Exceptions;

use LogicException;

final class VersionSequenceMismatchException extends LogicException
{
    /**
     * @param  list<int>  $expected
     * @param  list<int>  $persisted
     * @param  list<int>  $visible
     */
    public function __construct(array $expected, array $persisted, array $visible)
    {
        parent::__construct(sprintf(
            'Confirmed version sequences [%s] do not match persisted [%s] and visible [%s] sequences.',
            implode(', ', $expected),
            implode(', ', $persisted),
            implode(', ', $visible),
        ));
    }
}
