<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class SeedNode
{
    /**
     * @param  class-string  $seederClass
     * @param  list<class-string>  $dependsOn
     */
    public function __construct(
        public string $seederClass,
        public string $module,
        public array $dependsOn = [],
    ) {}
}
