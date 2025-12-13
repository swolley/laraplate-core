<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;

trait HasRecords
{
    protected function getHeaderActions(): array
    {
        $model = self::getResource()::getModel();
        $model_instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
        $model_table = $model_instance->getTable();
        $model_connection = $model_instance->getConnectionName() ?? 'default';
        $permissions_prefix = sprintf('%s.%s', $model_connection, $model_table);

        $can_create = Auth::user()->can($permissions_prefix . '.create');

        return $can_create ? [
            CreateAction::make()->icon(Heroicon::OutlinedPlus),
        ] : [];
    }
}
