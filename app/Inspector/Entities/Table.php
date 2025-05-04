<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

final class Table
{
    public readonly ?Index $primaryKey;

    /**
     * @param  Collection<Column>  $columns
     * @param  Collection<Index>  $indexes
     * @param  Collection<ForeignKey>  $foreignKeys
     */
    public function __construct(
        public readonly string $name,
        public readonly Collection $columns,
        public readonly Collection $indexes,
        public readonly Collection $foreignKeys,
        public readonly string $schema,
        public readonly ?string $connection = null,
    ) {
        $primaryKey = $indexes->filter(fn ($index) => $index->attributes->contains('primary'));

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
