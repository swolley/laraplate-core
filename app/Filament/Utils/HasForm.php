<?php

namespace Modules\Core\Filament\Utils;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Cms\Helpers\HasDynamicContents;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\SortableTrait;
use Spatie\EloquentSortable\SortableTrait as BaseSortableTrait;

trait HasForm
{
    protected static function configureForm(Schema $schema) 
    {
        /** @var User $user */
        $user = Auth::user();

        self::loadUserPermissionsForTable($user);

        $model = $schema->getModel();
        $model_instance = new $model();
        $model_table = $model_instance->getTable();
        $model_connection = $model_instance->getConnectionName() ?? 'default';
        $permissions_prefix = "{$model_connection}.{$model_table}";

        $traits = class_uses_recursive($model);
        $has_validity = in_array(HasValidity::class, $traits, true);
        $has_sorts = in_array(SortableTrait::class, $traits, true) || in_array(BaseSortableTrait::class, $traits, true);
        $has_dynamic_contents = in_array(HasDynamicContents::class, $traits, true);
    }
}