<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

trait HasModerationMeta
{
    protected function casts(): array
    {
        return [
            'meta' => 'json',
        ];
    }
}
