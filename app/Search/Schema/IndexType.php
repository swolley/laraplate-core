<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

enum IndexType: string
{
    case Searchable = 'searchable';
    case Filterable = 'filterable';
    case Sortable = 'sortable';
    case Facetable = 'facetable';
    case Vector = 'vector';
    case FullText = 'fulltext';
    case Fuzzy = 'fuzzy';
    case Prefix = 'prefix';
}
