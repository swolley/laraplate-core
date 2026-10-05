<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Modules\Core\Search\Contracts\ISearchPlanner;

/**
 * Planner stand-in that always asks for vector retrieval.
 */
final class VectorGuardPlannerStub implements ISearchPlanner
{
    public function safePlan(string $query): array
    {
        return $this->fallbackPlan($query);
    }

    public function fallbackPlan(string $query): array
    {
        return [
            'retrieval' => ['use_fulltext' => true, 'use_vector' => true, 'use_ensemble' => true, 'size' => 20],
            'ensemble' => ['keyword_weight' => 0.35, 'vector_weight' => 0.35, 'hybrid_weight' => 0.30],
            'ranking' => ['use_reranker' => false],
            'vector' => ['enabled' => true],
        ];
    }
}
