<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * Data Transfer Object for CRUD operation results.
 *
 * This DTO encapsulates the result of a CRUD operation, including data,
 * metadata, errors, and HTTP status codes.
 */
readonly class CrudResult
{
    public function __construct(
        public mixed $data,
        public ?CrudMeta $meta = null,
        public ?string $error = null,
        public ?int $statusCode = null,
    ) {}
}
