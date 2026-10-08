<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

final readonly class FiltersGroup
{
    public function __construct(
        /**
         * @var array<Filter|FiltersGroup|RelationFilter>
         */
        public array $filters = [],
        public WhereClause $operator = WhereClause::And,
    ) {}

    /**
     * @return array{filters: list<array<string, mixed>>, operator: string}
     */
    public function toArray(): array
    {
        $filters = [];

        foreach ($this->filters as $node) {
            $filters[] = $node->toArray();
        }

        return [
            'filters' => $filters,
            'operator' => $this->operator->value,
        ];
    }
}
