<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Contracts;

interface DeclaresSeedDependencies
{
    /**
     * Seeder classes that must complete before this one.
     *
     * Cross-module edges implied by module.json "requires" are added
     * automatically and need not be repeated here.
     *
     * @return list<class-string>
     */
    public static function dependsOn(): array;
}
