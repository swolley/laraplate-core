<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

final readonly class RestoreReport
{
    /**
     * @param  list<string>  $restoredRelations
     * @param  array<string, list<int|string>>  $skippedSubjects
     */
    public function __construct(
        public int $targetRevision,
        public array $restoredRelations,
        public array $skippedSubjects = [],
    ) {}

    public function isComplete(): bool
    {
        return $this->skippedSubjects === [];
    }
}
