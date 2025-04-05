<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

readonly class FiltersGroup
{
    public function __construct(
        /**
         *
         * @var array<Filter|FiltersGroup>
         */
        public array $filters,
        public WhereClause $operator = WhereClause::AND
    ) {}
}
