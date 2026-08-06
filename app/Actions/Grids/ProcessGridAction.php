<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Grids;

use Closure;
use Illuminate\Http\JsonResponse;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Services\Authorization\AuthorizationService;

final readonly class ProcessGridAction
{
    public function __construct(
        private AuthorizationService $auth,
        private ?Closure $entityResolver = null,
        private ?Closure $gridFactory = null,
    ) {}

    public function __invoke(object $request, string $entity, ?string $module = null): JsonResponse
    {
        $filters = method_exists($request, 'parsed') ? $request->parsed() : [];
        $connection = is_array($filters) ? ($filters['connection'] ?? null) : ($filters->connection ?? ($filters['connection'] ?? null));
        $actionValue = $this->extractActionValue($filters);

        $model = $this->resolveEntity($entity, $connection, $request, $module);
        $this->auth->ensurePermission($request, $model->getTable(), $actionValue, $model->getConnectionName());
        $grid = $this->gridFactory instanceof Closure ? ($this->gridFactory)($model) : new Grid($model);

        return $grid->process($request);
    }

    private function resolveEntity(string $entity, ?string $connection, object $request, ?string $module = null): mixed
    {
        if ($this->entityResolver instanceof Closure) {
            return ($this->entityResolver)($entity, $connection, $request, $module);
        }

        $http_request = $request instanceof \Illuminate\Http\Request ? $request : null;

        return DynamicEntity::resolve($entity, $connection, request: $http_request, module: $module);
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
