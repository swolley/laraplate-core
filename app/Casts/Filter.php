<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

final readonly class Filter
{
    public function __construct(
        public string $property,
        public mixed $value,
        public FilterOperator $operator = FilterOperator::Equals,
    ) {}

    /**
     * @return array{property: string, value: mixed, operator: string}
     */
    public function toArray(): array
    {
        return [
            'property' => $this->property,
            'value' => $this->value,
            'operator' => $this->operator->value,
        ];
    }
}
