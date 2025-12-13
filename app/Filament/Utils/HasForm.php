<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;

trait HasForm
{
    protected static function configureForm(Schema $schema): void
    {
        // TODO: da finire di scrivere

        /** @var User $user */
        $user = Auth::user();

        self::loadUserPermissionsForTable($user);

        $model = $schema->getModel();
        $model_instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
        $table = $model_instance->getTable();
        $connection = $model_instance->getConnectionName() ?? 'default';

        class_uses_recursive($model);
    }
}
