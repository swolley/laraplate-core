<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Modules\Core\Graph\Contracts\GraphProviderInterface;
use Modules\Core\Graph\Contracts\GraphProviderRulesInterface;

final class GraphServiceRulesProvider implements GraphProviderInterface, GraphProviderRulesInterface
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
        return ['permissions'];
    }

    public function maxDepth(string $module, string $entity): ?int
    {
        return 1;
    }

    public function maxRelationLimit(string $module, string $entity, string $relation): ?int
    {
        return null;
    }
}
