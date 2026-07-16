<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRulesInterface;

final class GraphProviderRulesTestProvider implements GraphProviderInterface, GraphProviderRulesInterface
{
    public function defaultRelations(string $module, string $entity): array
    {
        return [];
    }

    public function summaryFields(string $module, string $entity): array
    {
        return [];
    }

    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return null;
    }

    public function excludedRelations(string $module, string $entity): array
    {
        return [];
    }

    public function allowedRelationPaths(string $module, string $entity): array
    {
        return ['allowed', 'allowed.children'];
    }

    public function maxDepth(string $module, string $entity): ?int
    {
        return 2;
    }

    public function maxRelationLimit(string $module, string $entity, string $relation): ?int
    {
        return $relation === 'allowed' ? 3 : null;
    }
}
