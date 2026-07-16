<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Modules\Core\Graph\Contracts\GraphProviderInterface;

final class RegistryTestProvider implements GraphProviderInterface
{
    public function __construct(private readonly string $name) {}

    public function defaultRelations(string $module, string $entity): array
    {
        return [$this->name];
    }

    public function summaryFields(string $module, string $entity): array
    {
        return ['name'];
    }

    public function edgeType(string $module, string $entity, string $relation): ?string
    {
        return $this->name . ':' . $relation;
    }

    public function excludedRelations(string $module, string $entity): array
    {
        return [];
    }
}
