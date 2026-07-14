<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\DTOs\AnalyzedSearchToken;
use Modules\Core\Search\DTOs\SearchQueryAnalysis;
use Modules\Core\Search\Enums\SearchTokenKind;

final readonly class SearchQueryAnalyzer
{
    /**
     * @var list<string>
     */
    private const array STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to', 'with',
        'a', 'ai', 'al', 'alla', 'alle', 'anche', 'che', 'con', 'da', 'dal', 'dei', 'del', 'della', 'di', 'e', 'ed', 'gli', 'i',
        'il', 'in', 'la', 'le', 'lo', 'nel', 'nella', 'non', 'o', 'per', 'sono', 'su', 'tra', 'un', 'una', 'uno',
    ];

    public function analyze(string $query, int $minimumTermLength = 4): SearchQueryAnalysis
    {
        $parts = preg_split('/\s+/u', mb_trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = array_flip(self::STOPWORDS);
        $tokens = [];
        $significant = 0;
        $protected = 0;
        $eligible = 0;

        foreach (array_values($parts) as $position => $original) {
            $normalized = mb_strtolower(mb_trim($original, " \t\n\r\0\x0B.,;:!?()[]{}\"'"));
            $kind = $this->classify($original, $normalized, $minimumTermLength);
            $is_significant = $normalized !== '' && ! isset($stopwords[$normalized]);
            $token = new AnalyzedSearchToken($position, $original, $normalized, $kind, $is_significant);
            $tokens[] = $token;

            if (! $is_significant) {
                continue;
            }

            $significant++;

            if ($token->protectedByDefault()) {
                $protected++;
            } else {
                $eligible++;
            }
        }

        return new SearchQueryAnalysis($query, $tokens, $significant, $protected, $eligible);
    }

    private function classify(string $original, string $normalized, int $minimumTermLength): SearchTokenKind
    {
        if (filter_var($original, FILTER_VALIDATE_EMAIL) !== false) {
            return SearchTokenKind::Email;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $original) === 1) {
            return SearchTokenKind::Uuid;
        }

        if ($normalized !== '' && ctype_digit($normalized)) {
            return SearchTokenKind::Numeric;
        }

        if (preg_match('/[\p{L}\d]+[-_\/.][\p{L}\d_\/.-]*/u', $original) === 1
            || (preg_match('/\d/u', $original) === 1 && preg_match('/\p{L}/u', $original) === 1)) {
            return SearchTokenKind::StructuredIdentifier;
        }

        if (mb_strlen($original) >= 2
            && mb_strlen($original) <= 8
            && preg_match('/^[\p{Lu}\d]+$/u', $original) === 1
            && preg_match('/\p{Lu}/u', $original) === 1) {
            return SearchTokenKind::Acronym;
        }

        if (mb_strlen($normalized) < $minimumTermLength) {
            return SearchTokenKind::Short;
        }

        return SearchTokenKind::Word;
    }
}
