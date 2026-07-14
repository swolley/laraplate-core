<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\DTOs\ParsedSearchQuery;

/**
 * Parse the public `qs` operators without interpreting filters or engine syntax.
 *
 * Quoted text is a required adjacent phrase. A leading plus makes one term
 * required. Both constructs are literal and must never enter fuzzy expansion.
 *
 * We deliberately keep the familiar Google-style syntax at the public boundary,
 * then convert it once into a structured contract. This gives users predictable
 * operators while preventing Elasticsearch, Typesense, and SQL from assigning
 * different meanings to the same raw punctuation.
 */
final readonly class SearchQuerySyntaxParser
{
    public function parse(string $query): ParsedSearchQuery
    {
        $free = [];
        $required_terms = [];
        $required_phrases = [];
        $length = strlen($query);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && ctype_space($query[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $quoted_offset = $query[$offset] === '"'
                ? $offset
                : (($query[$offset] === '+' && ($query[$offset + 1] ?? null) === '"') ? $offset + 1 : null);

            if ($quoted_offset !== null) {
                [$phrase, $next_offset, $closed] = $this->readQuoted($query, $quoted_offset);

                if ($closed) {
                    if ($phrase !== '') {
                        $required_phrases[] = $phrase;
                    }

                    $offset = $next_offset;

                    continue;
                }

                $free[] = trim($this->unescape(substr($query, $quoted_offset + 1)));

                break;
            }

            $end = $offset;

            while ($end < $length && ! ctype_space($query[$end])) {
                $end++;
            }

            $token = $this->unescape(substr($query, $offset, $end - $offset));

            if (str_starts_with($token, '+') && mb_strlen($token) > 1) {
                $required_terms[] = mb_substr($token, 1);
            } else {
                $free[] = $token;
            }

            $offset = $end;
        }

        return new ParsedSearchQuery(
            freeText: trim(implode(' ', array_filter($free, static fn (string $value): bool => $value !== ''))),
            requiredTerms: array_values(array_unique(array_filter($required_terms, static fn (string $value): bool => $value !== ''))),
            requiredPhrases: array_values(array_unique($required_phrases)),
        );
    }

    /**
     * @return array{0: string, 1: int, 2: bool}
     */
    private function readQuoted(string $query, int $quoteOffset): array
    {
        $value = '';
        $length = strlen($query);
        $offset = $quoteOffset + 1;

        while ($offset < $length) {
            if ($query[$offset] === '\\' && ($query[$offset + 1] ?? null) !== null) {
                $value .= $query[$offset + 1];
                $offset += 2;

                continue;
            }

            if ($query[$offset] === '"') {
                return [trim($value), $offset + 1, true];
            }

            $value .= $query[$offset];
            $offset++;
        }

        return [$value, $offset, false];
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }
}
