<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Exceptions;

use RuntimeException;

final class MissingSeedDependencyException extends RuntimeException
{
    public static function for(string $seederClass, string $dependency): self
    {
        return new self(
            "Seeder {$seederClass} depends on {$dependency}, which is not present in the graph. "
            . 'Its owning module is disabled or absent.',
        );
    }
}
