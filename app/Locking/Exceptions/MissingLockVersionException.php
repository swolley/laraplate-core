<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class MissingLockVersionException extends RuntimeException
{
    public static function forModel(Model $model, string $column): self
    {
        return new self(sprintf(
            'Cannot update [%s]: the optimistic locking column [%s] was not loaded, so concurrent changes cannot be detected.',
            $model::class,
            $column,
        ));
    }
}
