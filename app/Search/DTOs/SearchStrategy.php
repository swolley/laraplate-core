<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchPlanner;
use Modules\Core\Search\Contracts\ITextEmbedder;
use Modules\Core\Search\Enums\SearchMode;

/**
 * The coherent set of components one search runs with, as decided by an {@see \Modules\Core\Search\Contracts\ISearchStrategyResolver}.
 *
 * Built as a whole so an incoherent mixture cannot be assembled by accident, and so the caller has what it needs to
 * fill the response meta. A null embedder means the vector stage is skipped.
 */
final readonly class SearchStrategy
{
    public function __construct(
        public SearchMode $applied_mode,
        public ISearchPlanner $planner,
        public IReranker $reranker,
        public IQueryIntentParser $intent_parser,
        public ?ITextEmbedder $embedder = null,
        public int $max_retries = 0,
        public ?string $degraded_reason = null,
    ) {}
}
