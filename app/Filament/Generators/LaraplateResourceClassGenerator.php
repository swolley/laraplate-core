<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Coolsam\Modules\Resource as CoolsamResource;
use Filament\Commands\FileGenerators\Resources\ResourceClassGenerator;
use Filament\Resources\Resource as FilamentResource;
use Override;

final class LaraplateResourceClassGenerator extends ResourceClassGenerator
{
    #[Override]
    public function getExtends(): string
    {
        return CoolsamResource::class;
    }

    /**
     * @return array<string>
     */
    #[Override]
    public function getImports(): array
    {
        $imports = parent::getImports();

        return array_map(
            static fn (mixed $import): mixed => $import === FilamentResource::class ? CoolsamResource::class : $import,
            $imports,
        );
    }
}
