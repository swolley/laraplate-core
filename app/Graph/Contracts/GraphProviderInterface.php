<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

interface GraphProviderInterface
{
    /**
     * @return list<string>
     */
    public function defaultRelations(string $module, string $entity): array;

    /**
     * @return list<string>
     */
    public function summaryFields(string $module, string $entity): array;

    public function edgeType(string $module, string $entity, string $relation): ?string;

    /**
     * @return list<string>
     */
    public function excludedRelations(string $module, string $entity): array;
}
