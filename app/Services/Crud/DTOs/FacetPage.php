<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * One page of an open facet's values. Each value carries the double counter
 * (`total` = distribution ignoring the request filters; `count` = current filter
 * state minus the facet's own selection) plus the display `attributes` resolved
 * for its key. `distinctValues` is the total number of distinct values under the
 * current filter/search, so the facet itself can be paginated.
 */
final readonly class FacetPage
{
    /**
     * @param  list<array{key: mixed, total: int, count: int, attributes: array<string, mixed>}>  $values
     */
    public function __construct(
        public array $values,
        public int $distinctValues,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @return array{values: list<array{key: mixed, total: int, count: int, attributes: array<string, mixed>}>, distinctValues: int, page: int, perPage: int}
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'distinctValues' => $this->distinctValues,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }
}
