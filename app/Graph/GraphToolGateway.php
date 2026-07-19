<?php

declare(strict_types=1);

namespace Modules\Core\Graph;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Casts\ExpandGraphRequestData;
use Modules\Core\Casts\SearchGraphRequestData;
use Modules\Core\Graph\Contracts\GraphToolGatewayInterface;
use Modules\Core\Graph\Data\GraphExpandToolInput;
use Modules\Core\Graph\Data\GraphSearchToolInput;
use Modules\Core\Graph\Data\GraphStatsToolInput;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Http\Requests\SearchGraphRequest;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Throwable;

final class GraphToolGateway implements GraphToolGatewayInterface
{
    /**
     * @param  (Closure(string, SearchGraphRequestData|ExpandGraphRequestData): CrudResult)|null  $executor
     */
    public function __construct(
        private readonly GraphService $graph,
        private readonly Request $request,
        private readonly ?Closure $executor = null,
    ) {}

    public function search(GraphSearchToolInput $input): array
    {
        try {
            $request = $this->searchRequest();
            $primaryKey = $this->primaryKey($input->module, $input->entity, $request);
            $data = new SearchGraphRequestData($request, $input->entity, [
                'qs' => $input->query,
                'limit' => $input->limit,
                'relations' => $input->relations,
                'depth' => $input->depth,
                'relation_limit' => $input->relationLimit,
                'node_detail' => 'summary',
            ], $primaryKey, $input->module);

            return $this->execute('search', $data);
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    public function expand(GraphExpandToolInput $input): array
    {
        try {
            return $this->executeForCenter('expand', $input);
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    public function stats(GraphStatsToolInput $input): array
    {
        try {
            return $this->executeForCenter('stats', $input);
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function executeForCenter(string $operation, GraphExpandToolInput|GraphStatsToolInput $input): array
    {
        $request = $this->expandRequest($input->recordKey);
        $primaryKey = $this->primaryKey($input->module, $input->entity, $request);
        $data = new ExpandGraphRequestData($request, $input->entity, [
            $primaryKey => $input->recordKey,
            'relations' => $input->relations,
            'depth' => $input->depth,
            'limit' => $input->limit,
            'relation_limit' => $input->relationLimit,
            'node_detail' => 'summary',
        ], $primaryKey, $input->module);

        return $this->execute($operation, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(string $operation, SearchGraphRequestData|ExpandGraphRequestData $data): array
    {
        $result = $this->executor instanceof Closure
            ? ($this->executor)($operation, $data)
            : $this->graph->{$operation}($data);

        if ($result->error !== null || $result->statusCode !== null || ! is_array($result->data)) {
            return $this->unavailable();
        }

        return $operation === 'stats'
            ? $this->sanitizeStats($result->data)
            : $this->sanitizeGraph($result->data);
    }

    private function searchRequest(): SearchGraphRequest
    {
        /** @var SearchGraphRequest $request */
        $request = SearchGraphRequest::create('/app/ai/graph/search', 'POST');
        $this->inheritAuthenticatedUser($request);

        return $request;
    }

    private function expandRequest(int|string $recordKey): ExpandGraphRequest
    {
        /** @var ExpandGraphRequest $request */
        $request = ExpandGraphRequest::create('/app/ai/graph/expand/' . urlencode((string) $recordKey), 'POST');
        $request->setRouteResolver(static fn (): object => new class($recordKey)
        {
            public function __construct(private readonly int|string $recordKey) {}

            public function parameter(string $name): int|string|null
            {
                return $name === 'id' ? $this->recordKey : null;
            }
        });
        $this->inheritAuthenticatedUser($request);

        return $request;
    }

    private function inheritAuthenticatedUser(Request $request): void
    {
        $request->setUserResolver(fn (): mixed => $this->request->user());
    }

    private function primaryKey(string $module, string $entity, Request $request): string
    {
        $key = DynamicEntity::resolve($entity, request: $request, module: $module)->getKeyName();

        return is_array($key) ? (string) head($key) : $key;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeGraph(array $data): array
    {
        $nodes = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        $edges = is_array($data['edges'] ?? null) ? $data['edges'] : [];
        $meta = is_array($data['graphMeta'] ?? null) ? $data['graphMeta'] : [];
        $searchMeta = is_array($data['searchMeta'] ?? null) ? $data['searchMeta'] : [];

        $output = [
            'available' => true,
            'nodes' => array_values(array_filter(array_map($this->sanitizeNode(...), $nodes))),
            'edges' => array_values(array_filter(array_map($this->sanitizeEdge(...), $edges))),
            'truncated' => (bool) ($meta['truncated'] ?? false),
        ];

        if (isset($data['center']) && is_string($data['center'])) {
            $output['center'] = $data['center'];
        }

        if (isset($searchMeta['resultCount']) && is_int($searchMeta['resultCount'])) {
            $output['result_count'] = $searchMeta['resultCount'];
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeStats(array $data): array
    {
        $graph = $this->sanitizeGraph($data);
        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];
        $graph['stats'] = [];

        foreach (['totalNodes', 'totalEdges', 'nodesByModule', 'nodesByEntity', 'edgesByRelation', 'edgesByType'] as $key) {
            if (is_int($stats[$key] ?? null)) {
                $graph['stats'][$key] = $stats[$key];
            } elseif (is_array($stats[$key] ?? null)) {
                $graph['stats'][$key] = array_filter($stats[$key], 'is_int');
            }
        }

        return $graph;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeNode(mixed $node): ?array
    {
        if (! is_array($node)
            || ! is_string($node['id'] ?? null)
            || ! is_string($node['module'] ?? null)
            || ! is_string($node['entity'] ?? null)) {
            return null;
        }

        $safeFields = config('graph.assistant_safe_fields', []);
        $specificKey = mb_strtolower($node['module'] . '.' . $node['entity']);
        $allowed = is_array($safeFields[$specificKey] ?? null)
            ? $safeFields[$specificKey]
            : ($safeFields['default'] ?? []);
        $attributes = is_array($node['attributes'] ?? null) ? $node['attributes'] : [];

        return [
            'id' => $node['id'],
            'module' => $node['module'],
            'entity' => $node['entity'],
            'label' => is_string($node['label'] ?? null) ? $node['label'] : null,
            'attributes' => array_intersect_key($attributes, array_flip(array_filter($allowed, 'is_string'))),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeEdge(mixed $edge): ?array
    {
        if (! is_array($edge)
            || ! is_string($edge['id'] ?? null)
            || ! is_string($edge['source'] ?? null)
            || ! is_string($edge['target'] ?? null)
            || ! is_string($edge['relation'] ?? null)) {
            return null;
        }

        return [
            'id' => $edge['id'],
            'source' => $edge['source'],
            'target' => $edge['target'],
            'relation' => $edge['relation'],
            'type' => is_string($edge['type'] ?? null) ? $edge['type'] : null,
            'directed' => (bool) ($edge['directed'] ?? true),
        ];
    }

    /**
     * @return array{available: false, nodes: array{}, edges: array{}, truncated: false}
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'nodes' => [],
            'edges' => [],
            'truncated' => false,
        ];
    }
}
