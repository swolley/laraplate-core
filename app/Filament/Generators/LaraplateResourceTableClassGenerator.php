<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Schemas\ResourceTableClassGenerator;
use Filament\Tables\Table;
use Modules\Core\Filament\FilamentTraitResolver;
use Nette\PhpGenerator\ClassType;
use Override;

final class LaraplateResourceTableClassGenerator extends ResourceTableClassGenerator
{
    /**
     * @return array<string>
     */
    #[Override]
    public function getImports(): array
    {
        return [
            ...parent::getImports(),
            FilamentTraitResolver::resolve($this->getFqn(), 'HasTable'),
        ];
    }

    #[Override]
    protected function addTraitsToClass(ClassType $class): void
    {
        $class->addTrait(FilamentTraitResolver::resolve($this->getFqn(), 'HasTable'));
    }

    #[Override]
    protected function addConfigureMethodToClass(ClassType $class): void
    {
        $method = $class->addMethod('configure')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Table::class)
            ->setBody(<<<'PHP'
                return self::configureTable(table: $table);
                PHP);
        $method->addParameter('table')
            ->setType(Table::class);
    }
}
