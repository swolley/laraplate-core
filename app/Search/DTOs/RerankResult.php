<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

/**
 * The scores of a rerank and, when the reranker says so, the model that produced them.
 */
final readonly class RerankResult
{
    /**
     * @param  list<float>  $scores  in [0, 1], same order as the pairs
     * @param  string|null  $model  null when the reranker does not name one
     */
    public function __construct(
        public array $scores,
        public ?string $model = null,
    ) {}
}
