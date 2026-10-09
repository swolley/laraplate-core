<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchPlanner;
use Modules\Core\Search\Contracts\ISearchStrategyResolver;
use Modules\Core\Search\Contracts\ITextEmbedder;
use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Override;

/**
 * Resolver double for tests: hands the same components back whatever the mode, optionally a `Deep` set of its own.
 */
final class FixedSearchStrategyResolver implements ISearchStrategyResolver
{
    public function __construct(
        private readonly ISearchPlanner $planner = new FallbackSearchPlanner,
        private readonly IQueryIntentParser $intent_parser = new SimpleQueryIntentParser,
        private readonly IReranker $reranker = new HeuristicReranker,
        private readonly ?ITextEmbedder $embedder = null,
        private readonly ?SearchStrategy $deep = null,
    ) {}

    #[Override]
    public function resolve(SearchMode $mode): SearchStrategy
    {
        if ($mode === SearchMode::Deep && $this->deep instanceof SearchStrategy) {
            return $this->deep;
        }

        return new SearchStrategy(
            applied_mode: SearchMode::Fast,
            planner: $this->planner,
            reranker: $this->reranker,
            intent_parser: $this->intent_parser,
            embedder: $this->embedder,
        );
    }
}
