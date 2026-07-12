<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

interface GraphProviderRulesInterface
{
    /**
     * Empty list means all requested relation paths are allowed unless excluded by the base provider.
     *
     * @return list<string>
     */
    public function allowedRelationPaths(string $module, string $entity): array;

    public function maxDepth(string $module, string $entity): ?int;

    public function maxRelationLimit(string $module, string $entity, string $relation): ?int;
}
