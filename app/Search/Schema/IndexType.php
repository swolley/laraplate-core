<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

enum IndexType: string
{
    case SEARCHABLE = 'searchable';
    case FILTERABLE = 'filterable';
    case SORTABLE = 'sortable';
    case FACETABLE = 'facetable';
    case VECTOR = 'vector';
}
