<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Contract for generating search execution plans.
 *
 * A search plan defines the retrieval strategy, ranking, ensemble weights,
 * vector settings, filters, and retry policy for a given query.
 */
interface ISearchPlanner
{
    /**
     * Generate a safe search plan with fallback on failure.
     *
     * @return array<string, mixed>  Structured search plan
     */
    public function safePlan(string $query): array;

    /**
     * Generate a rule-based fallback plan (no AI/LLM required).
     *
     * @return array<string, mixed>  Structured search plan
     */
    public function fallbackPlan(string $query): array;
}
