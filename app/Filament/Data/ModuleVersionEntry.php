<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Data;

final readonly class ModuleVersionEntry
{
    public function __construct(
        public string $name,
        public string $version,
        public bool $enabled,
        public bool $isApp = false,
    ) {}
}
