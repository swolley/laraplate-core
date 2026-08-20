<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

use Carbon\CarbonInterface;

/**
 * Metadata for CRUD operation results.
 *
 * Contains pagination information, record counts, and other metadata
 * related to the CRUD operation result.
 */
readonly class CrudMeta
{
    public function __construct(
        public ?int $totalRecords = null,
        public ?int $currentRecords = null,
        public ?int $currentPage = null,
        public ?int $totalPages = null,
        public ?int $pagination = null,
        public ?int $from = null,
        public ?int $to = null,
        /**
         * Whether a further page exists, set only when the exact total was skipped (`totals=false`).
         */
        public ?bool $hasMore = null,
        /**
         * Explicit pagination-counting mode, set on paginated (`page`) responses so the
         * client renders the right footer without inferring it from field presence.
         */
        public ?PaginationMode $mode = null,
        public ?string $class = null,
        public ?string $table = null,
        public ?CarbonInterface $cachedAt = null,
        /**
         * @var array<string, mixed>
         */
        public array $search = [],
    ) {}
}
