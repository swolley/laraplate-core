<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

/**
 * Whether vector retrieval can be used for a model right now and, when it cannot, why
 * (`disabled`, `suspended` or `dimension_mismatch`).
 */
final readonly class VectorAvailability
{
    private function __construct(
        public bool $available,
        public ?string $reason,
    ) {}

    public static function yes(): self
    {
        return new self(true, null);
    }

    public static function no(string $reason): self
    {
        return new self(false, $reason);
    }
}
