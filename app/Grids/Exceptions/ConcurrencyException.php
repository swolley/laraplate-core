<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class ConcurrencyException extends RuntimeException
{
    public function __construct(string $message = "You don't have the last version of the records")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
