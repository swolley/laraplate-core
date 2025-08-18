<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

final readonly class Filter
{
    public function __construct(
        public string $property,
        public mixed $value,
        public FilterOperator $operator = FilterOperator::EQUALS
    ) {}
}
