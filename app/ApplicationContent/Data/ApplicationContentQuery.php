<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Data;

use InvalidArgumentException;

final readonly class ApplicationContentQuery
{
    private const int HARD_MAX_QUERY_CHARS = 4000;

    private const int HARD_MAX_RESULTS = 50;

    public string $source;

    public string $query;

    public int $limit;

    public function __construct(
        string $source,
        string $query,
        public string $locale,
        int $limit,
    ) {
        $this->source = ApplicationContentSourceDescriptor::normalizeSource($source);
        $this->query = trim($query);
        $maximum_query_chars = min(
            self::HARD_MAX_QUERY_CHARS,
            max(1, (int) config('application-content.max_query_chars', 2000)),
        );

        if ($this->query === ''
            || ! mb_check_encoding($this->query, 'UTF-8')
            || mb_strlen($this->query) > $maximum_query_chars) {
            throw new InvalidArgumentException('Application content query is invalid.');
        }

        if (preg_match('/^[a-z]{2,3}(?:[-_][A-Z]{2})?$/', $this->locale) !== 1) {
            throw new InvalidArgumentException('Application content query locale is invalid.');
        }

        $maximum_results = min(
            self::HARD_MAX_RESULTS,
            max(1, (int) config('application-content.max_results', 8)),
        );
        $this->limit = max(1, min($limit, $maximum_results));
    }
}
