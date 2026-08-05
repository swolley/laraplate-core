<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

use Modules\Core\Enums\RelationOwnership;

final readonly class RelationDescriptor
{
    public function __construct(
        public string $relation,
        public RelationOwnership $ownership,
    ) {}

    public function isOwned(): bool
    {
        return $this->ownership === RelationOwnership::Owned;
    }
}
