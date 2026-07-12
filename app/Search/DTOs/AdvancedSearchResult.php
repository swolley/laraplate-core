<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

final readonly class AdvancedSearchResult
{
    /**
     * @param  list<array{id: string, score: float, source: array<string, mixed>}>  $hits
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $hits,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
        public array $meta = [],
    ) {}

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_map(
            static fn (array $hit): string => $hit['id'],
            $this->hits,
        ));
    }

    public static function empty(int $page = 1, int $perPage = 25, array $meta = []): self
    {
        return new self([], 0, $page, $perPage, 0, $meta);
    }
}
