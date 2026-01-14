<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

use Illuminate\Support\Carbon;

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
        public ?string $class = null,
        public ?string $table = null,
        public ?Carbon $cachedAt = null,
    ) {}
}
