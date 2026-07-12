<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;

final class GraphService
{
    public function __construct(
        private readonly AuthorizationService $auth,
        private readonly GraphTraversal $traversal,
        private readonly GraphProviderRegistryInterface $providers,
    ) {}

    public function expand(ExpandGraphRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        $permissionName = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $center = $this->findCenter($requestData, $permissionName);
        $relations = $this->relationsFor($requestData);

        $data = $this->traversal->expand(
            $center,
            $relations,
            $requestData->depth,
            $requestData->limit,
            $requestData->relationLimit,
            $requestData->nodeDetail,
            $requestData->request,
        );

        return new CrudResult(
            data: $data->toArray(),
            meta: new CrudMeta(
                class: $model::class,
                table: $model->getTable(),
                cachedAt: Date::now(),
            ),
        );
    }

    private function findCenter(ExpandGraphRequestData $requestData, string $permissionName): Model
    {
        $model = $requestData->model;
        $key = is_array($requestData->primaryKey) ? head($requestData->primaryKey) : $requestData->primaryKey;

        throw_if($requestData->recordKey === null || $requestData->recordKey === '', ModelNotFoundException::class, 'Primary key is required for graph expand.');

        $query = $model->newQuery()->where($key, $requestData->recordKey);
        $this->auth->applyAclFiltersToQuery($query, $permissionName);

        return $query->sole();
    }

    /**
     * @return list<string>
     */
    private function relationsFor(ExpandGraphRequestData $requestData): array
    {
        if ($requestData->graphRelations !== []) {
            return $requestData->graphRelations;
        }

        $module = strtolower((string) $requestData->module);
        $provider = $this->providers->providerFor($module, $requestData->mainEntity);

        return $provider?->defaultRelations($module, $requestData->mainEntity) ?? [];
    }
}
