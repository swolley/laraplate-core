<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

trait HasModerationMeta
{
    protected function casts(): array
    {
        return [
            'meta' => 'json',
        ];
    }
}
