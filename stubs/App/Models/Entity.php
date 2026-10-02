<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EntityType;
use Modules\Core\Models\Entity as CoreEntity;
use Override;

/**
 * Test-only App entity, resolved by Core's module naming convention for {@see EntityType}.
 */
final class Entity extends CoreEntity
{
    #[Override]
    protected static function getEntityTypeEnumClass(): string
    {
        return EntityType::class;
    }
}
