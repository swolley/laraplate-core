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

        // Scanning happens over characters, never over the raw string. `$query[$i]`
        // indexes bytes, so on an accented query a byte offset and a
        // character-counting mb_* call disagree by one position per accent, and the
        // parser hands back fragments of the words it was given: "città" arrives as
        // "tà", and the phrase after it loses its first characters too.
        $chars = mb_str_split($query);
        $length = count($chars);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && ctype_space($chars[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $quoted_offset = $chars[$offset] === '"'
                ? $offset
                : (($chars[$offset] === '+' && ($chars[$offset + 1] ?? null) === '"') ? $offset + 1 : null);

            if ($quoted_offset !== null) {
                [$phrase, $next_offset, $closed] = $this->readQuoted($chars, $quoted_offset);

                if ($closed) {
                    if ($phrase !== '') {
                        $required_phrases[] = $phrase;
                    }

                    $offset = $next_offset;

                    continue;
                }

                $free[] = mb_trim($this->unescape(implode('', array_slice($chars, $quoted_offset + 1))));

                break;
            }

            $end = $offset;

            while ($end < $length && ! ctype_space($chars[$end])) {
                $end++;
            }

            $token = $this->unescape(implode('', array_slice($chars, $offset, $end - $offset)));

            if (str_starts_with($token, '+') && mb_strlen($token) > 1) {
                $required_terms[] = mb_substr($token, 1);
            } else {
                $free[] = $token;
            }

            $offset = $end;
        }

        return new ParsedSearchQuery(
            freeText: mb_trim(implode(' ', array_filter($free, static fn (string $value): bool => $value !== ''))),
            requiredTerms: array_values(array_unique(array_filter($required_terms, static fn (string $value): bool => $value !== ''))),
            requiredPhrases: array_values(array_unique($required_phrases)),
        );
    }

    /**
     * @param  list<string>  $chars  the query split into characters
     * @return array{0: string, 1: int, 2: bool}
     */
    private function readQuoted(array $chars, int $quoteOffset): array
    {
        $value = '';
        $length = count($chars);
        $offset = $quoteOffset + 1;

        while ($offset < $length) {
            if ($chars[$offset] === '\\' && ($chars[$offset + 1] ?? null) !== null) {
                $value .= $chars[$offset + 1];
                $offset += 2;

                continue;
            }

            if ($chars[$offset] === '"') {
                return [mb_trim($value), $offset + 1, true];
            }

            $value .= $chars[$offset];
            $offset++;
        }

        return [$value, $offset, false];
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }
}
