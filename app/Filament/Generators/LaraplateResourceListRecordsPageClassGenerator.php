<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Pages\ResourceListRecordsPageClassGenerator;
use Modules\Core\Filament\FilamentTraitResolver;
use Nette\PhpGenerator\ClassType;
use Override;

final class LaraplateResourceListRecordsPageClassGenerator extends ResourceListRecordsPageClassGenerator
{
    /**
     * @return array<string>
     */
    #[Override]
    public function getImports(): array
    {
        $imports = parent::getImports();

        // HasRecords provides create header action — drop Filament CreateAction imports.
        $imports = array_values(array_filter(
            $imports,
            static fn (mixed $import): bool => ! (is_string($import) && str_ends_with($import, 'CreateAction')),
        ));

        $imports[] = FilamentTraitResolver::resolve($this->getFqn(), 'HasRecords');

        return $imports;
    }

    #[Override]
    protected function addTraitsToClass(ClassType $class): void
    {
        $class->addTrait(FilamentTraitResolver::resolve($this->getFqn(), 'HasRecords'));
    }

    #[Override]
    protected function addMethodsToClass(ClassType $class): void
    {
        // HasRecords supplies getHeaderActions(); do not emit Filament's duplicate method.
    }
}
