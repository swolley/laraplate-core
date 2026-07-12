<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\DTOs\GraphData;
use Modules\Core\Graph\DTOs\GraphEdge;
use Modules\Core\Graph\DTOs\GraphMeta;
use Modules\Core\Graph\DTOs\GraphNode;
use Modules\Core\Services\Authorization\AuthorizationService;

final class GraphTraversal
{
    /**
     * @var array<string, GraphNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, GraphEdge>
     */
    private array $edges = [];

    private bool $truncated = false;

    /**
     * @var list<string>
     */
    private array $truncatedBy = [];

    private bool $filteredByAcl = false;

    private bool $hasCycles = false;

    private int $deduplicatedNodeCount = 0;

    public function __construct(
        private readonly GraphRelationInspector $relations,
        private readonly GraphNodeSerializer $serializer,
        private readonly GraphEntityResolver $entities,
        private readonly GraphProviderRegistryInterface $providers,
        private readonly AuthorizationService $auth,
    ) {}

    /**
     * @param  list<string>  $relationPaths
     */
    public function expand(Model $center, array $relationPaths, int $depth, int $limit, int $relationLimit, string $nodeDetail, Request $request, bool $defaultRelationsApplied = false): GraphData
    {
        $this->reset();

        $centerNode = $this->addNode($center, $nodeDetail);

        foreach ($relationPaths as $path) {
            $segments = explode('.', $path);

            if (count($segments) > $depth) {
                throw ValidationException::withMessages(['relations' => 'Relation path exceeds depth.']);
            }

            $this->walk($center, $centerNode->id, $segments, $path, $limit, $relationLimit, $nodeDetail, $request, [$centerNode->id]);
        }

        return new GraphData(
            center: $centerNode->id,
            nodes: array_values($this->nodes),
            edges: array_values($this->edges),
            graphMeta: new GraphMeta(
                depth: $depth,
                requestedRelations: $relationPaths,
                defaultRelationsApplied: $defaultRelationsApplied,
                truncated: $this->truncated,
                truncatedBy: array_values(array_unique($this->truncatedBy)),
                filteredByAcl: $this->filteredByAcl,
                hasCycles: $this->hasCycles,
                deduplicatedNodeCount: $this->deduplicatedNodeCount,
            ),
        );
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $branch
     */
    private function walk(Model $source, string $sourceNodeId, array $segments, string $fullPath, int $limit, int $relationLimit, string $nodeDetail, Request $request, array $branch): void
    {
        if ($segments === [] || count($this->nodes) >= $limit) {
            return;
        }

        $relationName = array_shift($segments);
        $this->assertNotExcluded($source, $relationName);

        $relation = $this->relations->inspect($source, $relationName);

        if ($relation->isMorphTo) {
            $result = $relation->relation->getResults();

            if (! $result instanceof Model) {
                return;
            }

            $query = $result->newQuery()->whereKey($result->getKey());
            $targets = $this->visibleTargets($query, $result, $request)->get();
        } else {
            $query = $relation->relation->getQuery();
            $related = $relation->relation->getRelated();
            $query = $this->visibleTargets($query, $related, $request);

            if ($relation->isMultiple) {
                $targets = $query->limit($relationLimit + 1)->get();
            } else {
                $result = $query->first();
                $targets = new EloquentCollection($result instanceof Model ? [$result] : []);
            }
        }

        if ($targets->count() > $relationLimit) {
            $this->markTruncated('relation_limit');
            $targets = $targets->take($relationLimit);
        }

        foreach ($targets as $target) {
            if (! $target instanceof Model) {
                continue;
            }

            if (count($this->nodes) >= $limit) {
                $this->markTruncated('limit');

                return;
            }

            $targetNode = $this->addNode($target, $nodeDetail);
            $this->addEdge($sourceNodeId, $targetNode->id, $relationName, $fullPath, $source);

            if (in_array($targetNode->id, $branch, true)) {
                $this->hasCycles = true;

                continue;
            }

            $this->walk($target, $targetNode->id, $segments, $fullPath, $limit, $relationLimit, $nodeDetail, $request, [...$branch, $targetNode->id]);
        }
    }

    private function addNode(Model $model, string $nodeDetail): GraphNode
    {
        $node = $this->serializer->serialize($model, $nodeDetail);

        if (isset($this->nodes[$node->id])) {
            $this->deduplicatedNodeCount++;

            return $this->nodes[$node->id];
        }

        $this->nodes[$node->id] = $node;

        return $node;
    }

    private function addEdge(string $source, string $target, string $relation, string $fullPath, Model $sourceModel): void
    {
        $module = $this->entities->moduleFor($sourceModel);
        $entity = $this->entities->entityFor($sourceModel);
        $type = $this->providers->providerFor($module, $entity)?->edgeType($module, $entity, $relation);
        $edgeId = hash('xxh128', $source . '|' . $fullPath . '|' . $target . '|' . (string) $type);

        $this->edges[$edgeId] = new GraphEdge($edgeId, $source, $target, $relation, $type);
    }

    private function assertNotExcluded(Model $source, string $relation): void
    {
        $module = $this->entities->moduleFor($source);
        $entity = $this->entities->entityFor($source);
        $excluded = $this->providers->providerFor($module, $entity)?->excludedRelations($module, $entity) ?? [];

        if (in_array($relation, $excluded, true)) {
            throw ValidationException::withMessages(['relations' => sprintf("Relation '%s' is excluded by provider.", $relation)]);
        }
    }

    private function canSeeRelated(Request $request, Model $related): bool
    {
        return $this->auth->checkPermission($request, $related->getTable(), 'select', $related->getConnectionName());
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function visibleTargets(Builder $query, Model $related, Request $request): Builder
    {
        if (! $this->canSeeRelated($request, $related)) {
            $this->filteredByAcl = true;

            return $query->whereRaw('1 = 0');
        }

        $permissionName = $this->auth->buildPermissionName($related->getTable(), 'select', $related->getConnectionName());
        $visibleBeforeAcl = (clone $query)->count();
        $this->auth->applyAclFiltersToQuery($query, $permissionName);
        $visibleAfterAcl = (clone $query)->count();

        if ($visibleAfterAcl < $visibleBeforeAcl) {
            $this->filteredByAcl = true;
        }

        return $query;
    }

    private function markTruncated(string $reason): void
    {
        $this->truncated = true;
        $this->truncatedBy[] = $reason;
    }

    private function reset(): void
    {
        $this->nodes = [];
        $this->edges = [];
        $this->truncated = false;
        $this->truncatedBy = [];
        $this->filteredByAcl = false;
        $this->hasCycles = false;
        $this->deduplicatedNodeCount = 0;
    }
}
