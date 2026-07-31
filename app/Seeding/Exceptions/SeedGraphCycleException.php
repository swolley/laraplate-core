<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Exceptions;

use RuntimeException;

final class SeedGraphCycleException extends RuntimeException
{
    /**
     * @param  list<class-string>  $involved
     */
    public static function for(array $involved): self
    {
        return new self(
            'Seeder dependency cycle detected between: ' . implode(', ', $involved),
        );
    }
}
