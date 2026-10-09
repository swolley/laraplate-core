<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model whose serialization for some callers returns only part of its columns (the media projection of the
 * `api` guard). A request may then name only those columns where it reads values through the query instead of
 * the serialization: a facet, a group_by key, a sort, a filter or an aggregate. Otherwise the query would answer
 * what the serialization withholds.
 */
interface IRestrictsReadableColumns
{
    /**
     * The columns the current caller may read, or null when it may read every column.
     *
     * @return list<string>|null
     */
    public function readableColumnsForCurrentCaller(): ?array;
}
