<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\Contracts\IReranker;

/**
 * Non-AI reranker that uses text heuristics to score query-document relevance.
 *
 * Scoring strategy:
 *  - Exact phrase match in text → +0.4
 *  - Keyword overlap ratio → weight 0.4
 *  - Exact phrase in first 100 chars (title area) → +0.2
 */
final readonly class HeuristicReranker implements IReranker
{
    /**
     * @param  list<array{query: string, text: string}>  $pairs
     * @return list<float> Scores in [0, 1] range
     */
    public function score(array $pairs): array
    {
        $scores = [];

        foreach ($pairs as $pair) {
            $scores[] = $this->scorePair($pair['query'], $pair['text']);
        }

        return $scores;
    }

    /**
     * Calculate a heuristic relevance score for a single query-text pair.
     */
    private function scorePair(string $query, string $text): float
    {
        $normalized_query = mb_strtolower(mb_trim($query));
        $normalized_text = mb_strtolower(mb_trim($text));

        if ($normalized_query === '' || $normalized_text === '') {
            return 0.0;
        }

        $score = 0.0;

        if (str_contains($normalized_text, $normalized_query)) {
            $score += 0.4;
        }

        $score += $this->keywordOverlapScore($normalized_query, $normalized_text) * 0.4;

        $title_area = mb_substr($normalized_text, 0, 100);

        if (str_contains($title_area, $normalized_query)) {
            $score += 0.2;
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Ratio of query keywords found in the text.
     */
    private function keywordOverlapScore(string $query, string $text): float
    {
        $query_words = array_unique(preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if ($query_words === []) {
            return 0.0;
        }

        $text_words = array_flip(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $matched = 0;

        foreach ($query_words as $word) {
            if (isset($text_words[$word])) {
                $matched++;
            }
        }

        return $matched / count($query_words);
    }
}
