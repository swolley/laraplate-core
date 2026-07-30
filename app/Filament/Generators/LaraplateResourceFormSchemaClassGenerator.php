<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Schemas\ResourceFormSchemaClassGenerator;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Modules\Core\Filament\FilamentTraitResolver;
use Nette\PhpGenerator\ClassType;
use Override;

final class LaraplateResourceFormSchemaClassGenerator extends ResourceFormSchemaClassGenerator
{
    /**
     * @return array<string>
     */
    #[Override]
    public function getImports(): array
    {
        return [
            ...parent::getImports(),
            FilamentTraitResolver::resolve($this->getFqn(), 'HasForm'),
        ];
    }

    #[Override]
    protected function addTraitsToClass(ClassType $class): void
    {
        $class->addTrait(FilamentTraitResolver::resolve($this->getFqn(), 'HasForm'));
    }

    #[Override]
    protected function addConfigureMethodToClass(ClassType $class): void
    {
        $except_columns = array_values(array_unique(array_filter([
            ...Arr::wrap($this->getForeignKeyColumnToNotGenerate()),
            ...FilamentTraitResolver::formColumnsOwnedByHasForm($this->getModelFqn()),
        ])));

        $filament_body = $this->generateFormMethodBody(
            $this->getModelFqn(),
            exceptColumns: $except_columns,
        );

        $filament_body = preg_replace('/^return /', '', $filament_body, 1) ?? $filament_body;

        $filament_expression = rtrim(rtrim((string) $filament_body), ';');

        $method = $class->addMethod('configure')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Schema::class)
            ->setBody(<<<PHP
                return self::configureForm({$filament_expression});
                PHP);
        $method->addParameter('schema')
            ->setType(Schema::class);
    }
}
