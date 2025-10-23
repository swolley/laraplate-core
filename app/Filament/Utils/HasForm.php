<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

trait HasForm
{
    protected static function configureForm(Schema $schema): void
    {
        /** @var User $user */
        $user = Auth::user();

        self::loadUserPermissionsForTable($user);

        $model = $schema->getModel();
        $model_instance = new $model();
        $model_instance->getTable();
        $model_instance->getConnectionName() ?? 'default';

        class_uses_recursive($model);
    }
}
