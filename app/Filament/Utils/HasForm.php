<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Schemas\Schema;

trait HasForm
{
    protected static function configureForm(Schema $schema): void
    {
        // TODO: finish HasForm — body commented so incomplete wiring cannot break execution.
        //
        // /** @var User $user */
        // $user = Auth::user();
        //
        // self::loadUserPermissionsForTable($user);
        //
        // $model = $schema->getModel();
        // // $model_instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
        // // $model_instance->getTable();
        // // $model_instance->getConnectionName() ?? 'default';
        //
        // class_uses_recursive($model);
    }
}
