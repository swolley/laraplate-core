<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

final readonly class Table
{
    public ?Index $primaryKey;

    /**
     * @param  Collection<Column>  $columns
     * @param  Collection<Index>  $indexes
     * @param  Collection<ForeignKey>  $foreignKeys
     */
    public function __construct(
        public string $name,
        public Collection $columns,
        public Collection $indexes,
        public Collection $foreignKeys,
        public string $schema,
        public ?string $connection = null,
    ) {
        $primaryKey = $indexes->filter(static fn ($index) => $index->attributes->contains('primary'));

        $this->primaryKey = $primaryKey->isNotEmpty() ? $primaryKey->first() : null;
    }

    /**
     * @return Collection<Column>
     */
    public function getPrimaryKeyColumns(): Collection
    {
        return $this->columns->filter(fn ($c) => $this->primaryKey->columns->contains($c->name));
    }
}
