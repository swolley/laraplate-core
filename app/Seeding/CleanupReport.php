<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class CleanupReport
{
    /**
     * @param  list<string>  $hardDeleted
     * @param  list<string>  $softDeleted
     * @param  list<string>  $preserved
     */
    public function __construct(
        public array $hardDeleted = [],
        public array $softDeleted = [],
        public array $preserved = [],
    ) {}
}
