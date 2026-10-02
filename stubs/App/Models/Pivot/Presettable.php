<?php

declare(strict_types=1);

namespace App\Models\Pivot;

use App\Models\Entity;
use App\Models\Preset;
use Modules\Core\Models\Pivot\Presettable as CorePresettable;
use Override;

/**
 * Test-only App presettable, resolved by Core's module naming convention for App presets.
 */
final class Presettable extends CorePresettable
{
    #[Override]
    protected function presetModelClass(): string
    {
        return Preset::class;
    }

    #[Override]
    protected function entityModelClass(): string
    {
        return Entity::class;
    }
}
