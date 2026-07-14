<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

use Modules\Core\Search\Enums\SearchTokenKind;

final readonly class AnalyzedSearchToken
{
    public function __construct(
        public int $position,
        public string $original,
        public string $normalized,
        public SearchTokenKind $kind,
        public bool $significant,
    ) {}

    public function protectedByDefault(): bool
    {
        return $this->kind->protectedByDefault();
    }
}
