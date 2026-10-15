<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Coolsam\Modules\Resource as CoolsamResource;
use Filament\Commands\FileGenerators\Resources\ResourceClassGenerator;
use Filament\Resources\Resource as FilamentResource;
use Modules\Core\Filament\FilamentTraitResolver;
use Nette\PhpGenerator\ClassType;
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
        $imports = array_map(
            static fn (mixed $import): mixed => $import === FilamentResource::class ? CoolsamResource::class : $import,
            parent::getImports(),
        );

        $imports[] = FilamentTraitResolver::resolve($this->getFqn(), 'HasTranslatedResourceLabels');

        return $imports;
    }

    /**
     * Generated resources name themselves from the module lang file (`{group}.entities.{table}`).
     */
    #[Override]
    protected function addTraitsToClass(ClassType $class): void
    {
        $class->addTrait(FilamentTraitResolver::resolve($this->getFqn(), 'HasTranslatedResourceLabels'));
    }
}
