<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Generators;

use Filament\Commands\FileGenerators\Resources\Pages\ResourceCreateRecordPageClassGenerator;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Nette\PhpGenerator\ClassType;
use Override;

final class LaraplateResourceCreateRecordPageClassGenerator extends ResourceCreateRecordPageClassGenerator
{
    /**
     * @return array<string>
     */
    #[Override]
    public function getImports(): array
    {
        return [...parent::getImports(), HasCloseOrCancelFormAction::class];
    }

    #[Override]
    protected function addTraitsToClass(ClassType $class): void
    {
        $class->addTrait(HasCloseOrCancelFormAction::class);
    }
}
