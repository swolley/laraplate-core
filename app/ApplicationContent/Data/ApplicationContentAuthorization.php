<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Data;

use InvalidArgumentException;
use Modules\Core\Casts\FiltersGroup;

final readonly class ApplicationContentAuthorization
{
    public function __construct(
        public string $permissionName,
        public ?FiltersGroup $filters,
    ) {
        if (mb_trim($this->permissionName) === '') {
            throw new InvalidArgumentException('Application content authorization is invalid.');
        }
    }
}
