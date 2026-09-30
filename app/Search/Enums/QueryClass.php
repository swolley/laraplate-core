<?php

declare(strict_types=1);

namespace Modules\Core\Search\Enums;

use Modules\Core\Search\DTOs\AnalyzedSearchToken;
use Modules\Core\Search\DTOs\SearchQueryAnalysis;

/**
 * Coarse query shape used to select a retrieval tuning parameter set.
 *
 * Derived only from what {@see \Modules\Core\Search\Services\SearchQueryAnalyzer} already
 * produced, in this precedence:
 *
 * 1. `identifier`: at least one significant code-like token (numeric, UUID, email, structured
 *    identifier). Short words and acronyms are protected from fuzziness by the text-matching
 *    layer but are not codes, so they do not make a query an identifier lookup.
 * 2. `short_keyword`: at most two significant tokens (names and labels).
 * 3. `multi_term`: three to five significant tokens and fewer than two stopwords.
 * 4. `natural_language`: six or more significant tokens, or at least two stopwords.
 */
enum QueryClass: string
{
    case Identifier = 'identifier';
    case ShortKeyword = 'short_keyword';
    case MultiTerm = 'multi_term';
    case NaturalLanguage = 'natural_language';

    /**
     * @var list<SearchTokenKind>
     */
    private const array IDENTIFIER_KINDS = [
        SearchTokenKind::Numeric,
        SearchTokenKind::Uuid,
        SearchTokenKind::Email,
        SearchTokenKind::StructuredIdentifier,
    ];

    public static function fromAnalysis(SearchQueryAnalysis $analysis): self
    {
        $has_identifier = array_any(
            $analysis->tokens,
            static fn (AnalyzedSearchToken $token): bool => $token->significant
                && in_array($token->kind, self::IDENTIFIER_KINDS, true),
        );

        if ($has_identifier) {
            return self::Identifier;
        }

        $significant = $analysis->significantTokenCount;

        if ($significant <= 2) {
            return self::ShortKeyword;
        }

        $stopwords = count($analysis->tokens) - $significant;

        if ($significant <= 5 && $stopwords < 2) {
            return self::MultiTerm;
        }

        return self::NaturalLanguage;
    }
}
