<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * How a paginated list response was counted, so the client can render the right
 * footer without inferring it from which meta fields are present.
 */
enum PaginationMode: string
{
    /**
     * Exact total computed (`COUNT(*)`): `totalRecords`/`totalPages` are populated.
     */
    case Counted = 'counted';

    /**
     * Total skipped (`totals=false`): only `hasMore` tells whether a next page exists.
     */
    case Lookahead = 'lookahead';
}
