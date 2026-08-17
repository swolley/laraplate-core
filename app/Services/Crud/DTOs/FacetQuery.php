<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * One open (high-cardinality) facet request: what key to group and count on, the
 * display fields to resolve per key, and the facet's own pagination, value
 * search and ordering. This is the tier-2 counterpart to the flat, enumerable
 * multi-facet counts — a facet is a paginated sub-list of values, not a flat
 * aggregate, so it carries its own page window.
 */
final readonly class FacetQuery
{
    private const int DEFAULT_PER_PAGE = 20;

    private const int MAX_PER_PAGE = 200;

    /**
     * @param  list<string>  $fields  Display fields resolved per key in a bounded second query:
     *                                base-table columns, or a single-hop `relation.column` whose
     *                                relation is a BelongsTo keyed by `groupBy` (label search/sort deferred).
     */
    public function __construct(
        public string $groupBy,
        public array $fields = [],
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $search = null,
        public FacetSort $sort = FacetSort::CountDesc,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $fields = [];

        if (is_array($input['fields'] ?? null)) {
            foreach ($input['fields'] as $field) {
                if (is_string($field) && $field !== '') {
                    $fields[] = $field;
                }
            }
        }

        $search = $input['search'] ?? null;

        return new self(
            groupBy: (string) ($input['groupBy'] ?? ''),
            fields: array_values(array_unique($fields)),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: max(1, min(self::MAX_PER_PAGE, (int) ($input['perPage'] ?? self::DEFAULT_PER_PAGE))),
            search: is_string($search) && $search !== '' ? $search : null,
            sort: FacetSort::tryFrom((string) ($input['sort'] ?? '')) ?? FacetSort::CountDesc,
        );
    }
}
