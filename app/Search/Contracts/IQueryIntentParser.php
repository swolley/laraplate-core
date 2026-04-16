<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Contract for parsing search query intent.
 *
 * Extracts structured information (keywords, expanded query, filters)
 * from a raw user query string.
 */
interface IQueryIntentParser
{
    /**
     * Parse a raw query into structured intent.
     *
     * @return array{keywords: list<string>, date_range: ?array<string, string>, query: array{expanded: string}}
     */
    public function parse(string $query): array;
}
