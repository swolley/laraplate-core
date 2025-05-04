<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

final readonly class Sort
{
    public SortDirection $direction;

    public function __construct(public string $property, string|SortDirection $type = SortDirection::ASC)
    {
        $this->direction = $type instanceof SortDirection ? $type : SortDirection::tryFrom(mb_strtolower($type));
    }
}
