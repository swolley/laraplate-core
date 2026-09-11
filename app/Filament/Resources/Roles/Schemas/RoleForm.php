<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Authorization\PermissionEnforcement;
use Modules\Core\Authorization\PermissionNote;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Models\Permission;

final class RoleForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('guard_name')
                    ->required()
                    ->maxLength(255)
                    ->default('web'),
                TextInput::make('description')
                    ->maxLength(255),
                // The label carries the warning because this is where the grant is made:
                // a permission whose capability is switched off in settings still binds
                // to the role, and changes nothing until somebody switches it back on.
                Select::make('permissions')
                    ->multiple()
                    ->relationship('permissions', 'name')
                    ->getOptionLabelFromRecordUsing(static function (Permission $record): string {
                        $name = (string) $record->name;
                        $note = app(PermissionEnforcement::class)->noteFor($name, $record->table_name);

                        return $note instanceof PermissionNote ? sprintf('%s (%s)', $name, $note->label) : $name;
                    })
                    ->preload(),
            ]);
    }
}
