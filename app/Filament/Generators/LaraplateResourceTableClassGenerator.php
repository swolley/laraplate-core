<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Schemas\ResourceTableClassGenerator;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
            Collection::class,
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
        $except_columns = array_values(array_unique(array_filter([
            ...Arr::wrap($this->getForeignKeyColumnToNotGenerate()),
            ...FilamentTraitResolver::tableColumnsOwnedByHasTable($this->getModelFqn()),
        ])));

        $column_expressions = $this->getTableColumns($this->getModelFqn(), $except_columns);

        if ($column_expressions === []) {
            $body = <<<'PHP'
                return self::configureTable(table: $table);
                PHP;
        } else {
            $columns_block = implode("\n                        ", $column_expressions);
            $body = <<<PHP
                return self::configureTable(
                    table: \$table,
                    columns: static function (Collection \$default_columns): void {
                        \$default_columns->unshift(
                            {$columns_block}
                        );
                    },
                );
                PHP;
        }

        $method = $class->addMethod('configure')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Table::class)
            ->setBody($body);
        $method->addParameter('table')
            ->setType(Table::class);
    }
}
