<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Casts\SearchGraphRequestData;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;

final class GraphService
{
    private readonly GraphProviderRuleEnforcer $rules;

    public function __construct(
        private readonly AuthorizationService $auth,
        private readonly GraphTraversal $traversal,
        private readonly GraphProviderRegistryInterface $providers,
        private readonly CrudService $crud,
        private readonly GraphNodeSerializer $serializer,
        private readonly GraphStatsCalculator $stats,
        ?GraphProviderRuleEnforcer $rules = null,
    ) {
        $this->rules = $rules ?? new GraphProviderRuleEnforcer(new GraphEntityResolver(), $this->providers);
    }

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
        [$relations, $defaultRelationsApplied] = $this->relationsFor($requestData);
        $this->rules->assertRequestAllowed($center, $relations, $requestData->depth, $requestData->relationLimit);

        $data = $this->traversal->expand(
            $center,
            $relations,
            $requestData->depth,
            $requestData->limit,
            $requestData->relationLimit,
            $requestData->nodeDetail,
            $requestData->request,
            $defaultRelationsApplied,
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

    public function search(SearchGraphRequestData $requestData): CrudResult
    {
        $result = $this->crud->search($requestData);

        if ($result->error !== null || $result->statusCode !== null) {
            return $result;
        }

        $models = $this->modelsFromSearchResult($result->data);
        $data = $requestData->graphRelations === []
            ? $this->graphFromSearchModels($models, $requestData)
            : $this->expandedGraphFromSearchModels($models, $requestData);

        return new CrudResult(
            data: $data,
            meta: $result->meta,
        );
    }

    public function stats(ExpandGraphRequestData $requestData): CrudResult
    {
        $result = $this->expand($requestData);
        $graph = is_array($result->data) ? $result->data : [];

        return new CrudResult(
            data: [
                'center' => $graph['center'] ?? null,
                'stats' => $this->stats->fromGraph($graph)->toArray(),
                'graphMeta' => $graph['graphMeta'] ?? [],
            ],
            meta: $result->meta,
            error: $result->error,
            statusCode: $result->statusCode,
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
     * @return array{0: list<string>, 1: bool}
     */
    private function relationsFor(ExpandGraphRequestData $requestData): array
    {
        if ($requestData->graphRelations !== []) {
            return [$requestData->graphRelations, false];
        }

        $module = strtolower((string) $requestData->module);
        $provider = $this->providers->providerFor($module, $requestData->mainEntity);
        $relations = $provider?->defaultRelations($module, $requestData->mainEntity) ?? [];

        return [$relations, $relations !== []];
    }

    /**
     * @return Collection<int, Model>
     */
    private function modelsFromSearchResult(mixed $data): Collection
    {
        if ($data instanceof Collection) {
            return $data->filter(static fn (mixed $item): bool => $item instanceof Model)->values();
        }

        if (is_iterable($data)) {
            return collect($data)->filter(static fn (mixed $item): bool => $item instanceof Model)->values();
        }

        return collect();
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<string, mixed>
     */
    private function graphFromSearchModels(Collection $models, SearchGraphRequestData $requestData): array
    {
        $nodes = $models
            ->map(fn (Model $model): array => $this->serializer->serialize($model, $requestData->nodeDetail)->toArray())
            ->unique('id')
            ->values()
            ->all();

        return $this->searchGraphPayload(
            nodes: $nodes,
            edges: [],
            requestData: $requestData,
            resultCount: $models->count(),
        );
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<string, mixed>
     */
    private function expandedGraphFromSearchModels(Collection $models, SearchGraphRequestData $requestData): array
    {
        $nodes = [];
        $edges = [];
        $truncated = false;
        $truncatedBy = [];
        $filteredByAcl = false;
        $hasCycles = false;
        $deduplicatedNodeCount = 0;
        $limit = (int) config('graph.default_limit', 100);

        foreach ($models as $model) {
            $this->rules->assertRequestAllowed($model, $requestData->graphRelations, $requestData->depth, $requestData->relationLimit);

            $graph = $this->traversal->expand(
                $model,
                $requestData->graphRelations,
                $requestData->depth,
                $limit,
                $requestData->relationLimit,
                $requestData->nodeDetail,
                $requestData->request,
            )->toArray();

            foreach ($graph['nodes'] as $node) {
                if (is_array($node) && isset($node['id']) && is_string($node['id'])) {
                    if (isset($nodes[$node['id']])) {
                        $deduplicatedNodeCount++;
                    }

                    $nodes[$node['id']] = $node;
                }
            }

            foreach ($graph['edges'] as $edge) {
                if (is_array($edge) && isset($edge['id']) && is_string($edge['id'])) {
                    $edges[$edge['id']] = $edge;
                }
            }

            $meta = is_array($graph['graphMeta'] ?? null) ? $graph['graphMeta'] : [];
            $truncated = $truncated || (bool) ($meta['truncated'] ?? false);
            $filteredByAcl = $filteredByAcl || (bool) ($meta['filteredByAcl'] ?? false);
            $hasCycles = $hasCycles || (bool) ($meta['hasCycles'] ?? false);
            $deduplicatedNodeCount += (int) ($meta['deduplicatedNodeCount'] ?? 0);

            if (is_array($meta['truncatedBy'] ?? null)) {
                array_push($truncatedBy, ...$meta['truncatedBy']);
            }
        }

        return $this->searchGraphPayload(
            nodes: array_values($nodes),
            edges: array_values($edges),
            requestData: $requestData,
            resultCount: $models->count(),
            truncated: $truncated,
            truncatedBy: array_values(array_unique(array_filter($truncatedBy, 'is_string'))),
            filteredByAcl: $filteredByAcl,
            hasCycles: $hasCycles,
            deduplicatedNodeCount: $deduplicatedNodeCount,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<string>  $truncatedBy
     * @return array<string, mixed>
     */
    private function searchGraphPayload(
        array $nodes,
        array $edges,
        SearchGraphRequestData $requestData,
        int $resultCount,
        bool $truncated = false,
        array $truncatedBy = [],
        bool $filteredByAcl = false,
        bool $hasCycles = false,
        int $deduplicatedNodeCount = 0,
    ): array {
        return [
            'center' => null,
            'nodes' => $nodes,
            'edges' => $edges,
            'graphMeta' => [
                'depth' => $requestData->depth,
                'requestedRelations' => $requestData->graphRelations,
                'defaultRelationsApplied' => false,
                'truncated' => $truncated,
                'truncatedBy' => $truncatedBy,
                'filteredByAcl' => $filteredByAcl,
                'hasCycles' => $hasCycles,
                'deduplicatedNodeCount' => $deduplicatedNodeCount,
            ],
            'searchMeta' => [
                'resultCount' => $resultCount,
            ],
        ];
    }
}
