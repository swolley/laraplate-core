<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Schemas\ResourceInfolistSchemaClassGenerator;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Modules\Core\Filament\FilamentTraitResolver;
use Nette\PhpGenerator\ClassType;
use Override;

/**
 * Keeps platform bookkeeping out of generated infolists.
 *
 * The form generator already excludes these columns; without the same treatment
 * here, a detail page would still display `is_deleted` and the lock version —
 * values that mean nothing to a reader and exist only for the framework.
 */
final class LaraplateResourceInfolistSchemaClassGenerator extends ResourceInfolistSchemaClassGenerator
{
    #[Override]
    protected function addConfigureMethodToClass(ClassType $class): void
    {
        $except_columns = array_values(array_unique(array_filter([
            ...Arr::wrap($this->getForeignKeyColumnToNotGenerate()),
            ...FilamentTraitResolver::platformColumnsNeverInForms($this->getModelFqn()),
        ])));

        $method = $class->addMethod('configure')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Schema::class)
            ->setBody($this->generateInfolistMethodBody($this->getModelFqn(), exceptColumns: $except_columns));

        $method->addParameter('schema')
            ->setType(Schema::class);

        $this->configureConfigureMethod($method);
    }
}
