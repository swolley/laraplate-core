<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SearchGraphRequest;

class SearchGraphRequestData extends SearchRequestData
{
    /**
     * @var list<string>
     */
    public readonly array $graphRelations;

    public readonly int $depth;

    public readonly int $relationLimit;

    public readonly string $nodeDetail;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(SearchGraphRequest $request, ?string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey, $module);

        $this->graphRelations = array_values($validated['relations'] ?? []);
        $this->depth = (int) ($validated['depth'] ?? $this->deriveDepth($this->graphRelations));
        $this->relationLimit = (int) ($validated['relation_limit'] ?? config('graph.default_relation_limit', 25));
        $this->nodeDetail = (string) ($validated['node_detail'] ?? config('graph.default_node_detail', 'summary'));
    }

    /**
     * @param  list<string>  $relations
     */
    private function deriveDepth(array $relations): int
    {
        if ($relations === []) {
            return 1;
        }

        return max(array_map(static fn (string $relation): int => substr_count($relation, '.') + 1, $relations));
    }
}
