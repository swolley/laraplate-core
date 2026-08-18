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
     *                                relation is a BelongsTo keyed by `groupBy`.
     * @param  ?string  $labelField  A single-hop `relation.column` (BelongsTo keyed by `groupBy`) to
     *                               search and sort by instead of the raw key; enables the LabelAsc/
     *                               LabelDesc sorts and makes `search` match the label. For a relation
     *                               facet it is a bare column on the related table.
     * @param  ?string  $relation  A BelongsToMany/MorphToMany relation to facet over its pivot instead
     *                             of a base column: keys are the related model ids, `fields`/`labelField`
     *                             resolve on the related table, and the double counter counts distinct
     *                             parent rows per related key.
     */
    public function __construct(
        public string $groupBy,
        public array $fields = [],
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $search = null,
        public FacetSort $sort = FacetSort::CountDesc,
        public ?string $labelField = null,
        public ?string $relation = null,
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
        $label_field = $input['labelField'] ?? null;
        $relation = $input['relation'] ?? null;

        return new self(
            groupBy: (string) ($input['groupBy'] ?? ''),
            fields: array_values(array_unique($fields)),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: max(1, min(self::MAX_PER_PAGE, (int) ($input['perPage'] ?? self::DEFAULT_PER_PAGE))),
            search: is_string($search) && $search !== '' ? $search : null,
            sort: FacetSort::tryFrom((string) ($input['sort'] ?? '')) ?? FacetSort::CountDesc,
            labelField: is_string($label_field) && $label_field !== '' ? $label_field : null,
            relation: is_string($relation) && $relation !== '' ? $relation : null,
        );
    }
}
