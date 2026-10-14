<?php

declare(strict_types=1);

namespace Modules\Core\Search\Support;

/**
 * What went wrong inside one search without stopping it: a model that timed out, an embedder or a reranker
 * that threw. The component that falls back notes the reason here, and `AdvancedSearchService` reports the first
 * one as `meta.search.degraded_reason`, so a degraded search says so even though no exception reached it.
 *
 * Bound as a scoped instance: one per request or queued job, emptied at the start of every search.
 */
final class SearchDegradations
{
    public const string LLM_UNAVAILABLE = 'llm_unavailable';

    public const string EMBEDDER_FAILED = 'embedder_failed';

    public const string RERANKER_FAILED = 'reranker_failed';

    /**
     * @var list<string>
     */
    private array $reasons = [];

    public function note(string $reason): void
    {
        if (! in_array($reason, $this->reasons, true)) {
            $this->reasons[] = $reason;
        }
    }

    public function first(): ?string
    {
        return $this->reasons[0] ?? null;
    }

    public function reset(): void
    {
        $this->reasons = [];
    }
}
