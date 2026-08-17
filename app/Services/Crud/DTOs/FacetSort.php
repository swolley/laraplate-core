<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * How an open facet's value page is ordered. Ordering is by the grouped count or
 * by the group key only — ordering by a resolved label is deferred (it would
 * force the label into the aggregated query).
 */
enum FacetSort: string
{
    case CountDesc = 'count_desc';
    case CountAsc = 'count_asc';
    case KeyAsc = 'key_asc';
    case KeyDesc = 'key_desc';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
