<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class ReconciliationOutcome
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $realigned
     * @param  list<string>  $restored
     */
    public function __construct(
        public array $created = [],
        public array $realigned = [],
        public array $restored = [],
        public int $unchanged = 0,
    ) {}

    public function touchedAnything(): bool
    {
        return $this->created !== [] || $this->realigned !== [] || $this->restored !== [];
    }
}
