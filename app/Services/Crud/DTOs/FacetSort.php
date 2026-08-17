<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * How an open facet's value page is ordered: by the grouped count, by the group
 * key, or by the resolved relation label (via a correlated subquery, so no join
 * is added to the aggregated query).
 */
enum FacetSort: string
{
    case CountDesc = 'count_desc';
    case CountAsc = 'count_asc';
    case KeyAsc = 'key_asc';
    case KeyDesc = 'key_desc';
    case LabelAsc = 'label_asc';
    case LabelDesc = 'label_desc';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
