<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

/**
 * What to tell someone about to grant a permission that will not do what its name says.
 */
final readonly class PermissionNote
{
    public function __construct(
        public string $label,
        public string $reason,
    ) {}
}
