<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Grids;

use Illuminate\Http\JsonResponse;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Helpers\PermissionChecker;
use Modules\Core\Models\DynamicEntity;
use UnexpectedValueException;

final class ProcessGridAction
{
    public function __construct(
        private readonly ?\Closure $entityResolver = null,
        private readonly ?\Closure $gridFactory = null,
    ) {
    }

    public function __invoke(object $request, string $entity): JsonResponse
    {
        $filters = method_exists($request, 'parsed') ? $request->parsed() : [];
        $connection = is_array($filters) ? ($filters['connection'] ?? null) : ($filters->connection ?? ($filters['connection'] ?? null));
        $actionValue = $this->extractActionValue($filters);

        $model = $this->resolveEntity($entity, $connection, $request);
        PermissionChecker::ensurePermissions($request, $model->getTable(), $actionValue, $model->getConnectionName());
        $grid = $this->gridFactory ? ($this->gridFactory)($model) : new Grid($model);

        return $grid->process($request);
    }

    private function resolveEntity(string $entity, ?string $connection, object $request)
    {
        if ($this->entityResolver) {
            return ($this->entityResolver)($entity, $connection, $request);
        }

        return DynamicEntity::resolve($entity, $connection, request: $request);
    }

    private function extractActionValue(mixed $filters): string
    {
        if (is_object($filters) && isset($filters->action) && isset($filters->action->value)) {
            return $filters->action->value;
        }

        if (is_array($filters) && isset($filters['action']) && isset($filters['action']->value)) {
            return $filters['action']->value;
        }

        return 'select';
    }
}

