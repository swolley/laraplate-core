<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a generic CRUD write is attempted on a model that restricts it.
 * Mapped to HTTP 403 by {@see \Modules\Core\Http\Controllers\CrudController}.
 */
final class CrudWriteNotAllowedException extends RuntimeException
{
    public static function for(Model $model, string $operation): self
    {
        return new self(sprintf(
            'The "%s" operation is not allowed on "%s" through the generic CRUD API.',
            $operation,
            $model->getTable(),
        ));
    }
}
