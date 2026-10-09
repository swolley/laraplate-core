<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\Contracts\ISearchStrategyResolver;
use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;
use Override;

/**
 * Core's own strategy: cheap components, whatever mode is asked for.
 *
 * It takes the concrete cheap classes, not the contracts, so a module that rebinds a contract cannot make this
 * resolver expensive. The mode is ignored on purpose, not because this is a stub. Core has nothing more expensive to offer, so a
 * `Deep` request is served as `Fast` and says so through `mode_unavailable`.
 */
final readonly class CoreSearchStrategyResolver implements ISearchStrategyResolver
{
    public function __construct(
        private FallbackSearchPlanner $planner,
        private HeuristicReranker $reranker,
        private SimpleQueryIntentParser $intent_parser,
    ) {}

    #[Override]
    public function resolve(SearchMode $mode): SearchStrategy
    {
        return new SearchStrategy(
            applied_mode: SearchMode::Fast,
            planner: $this->planner,
            reranker: $this->reranker,
            intent_parser: $this->intent_parser,
            degraded_reason: $mode === SearchMode::Fast ? null : 'mode_unavailable',
        );
    }
}
