<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Contract for reranking search results.
 *
 * Implementations may use AI-based cross-encoders, heuristic text matching,
 * or any other scoring strategy.
 */
interface IReranker
{
    /**
     * Score query-document pairs for reranking.
     *
     * @param  list<array{query: string, text: string}>  $pairs
     * @return list<float>  Scores in [0, 1] range, same order as input pairs
     */
    public function score(array $pairs): array;
}
