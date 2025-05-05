<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

final readonly class Index
{
    /**
     * @param  Collection<string>  $columns
     * @param  Collection<string>  $attributes
     */
    public function __construct(
        public string $name,
        public Collection $columns,
        public Collection $attributes,
    ) {}

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }

    public function isPrimaryKey(): bool
    {
        return $this->attributes->contains('primary');
    }

    public function isCompositePrimaryKey(): bool
    {
        return $this->isPrimaryKey() && $this->isComposite();
    }
}
