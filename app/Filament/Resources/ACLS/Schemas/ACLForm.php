<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Rules\QueryBuilder;

final class ACLForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                Select::make('permission_id')
                    ->relationship('permission', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Leave empty to apply the ACL to every role holding the permission.'),
                Toggle::make('unrestricted')
                    ->live()
                    ->helperText('Grants full access: stored filters are ignored.'),
                CodeEditor::make('filters')
                    ->language(Language::Json)
                    ->helperText('Filters group as JSON, e.g. {"operator":"and","filters":[{"property":"status","operator":"=","value":"published"}]}')
                    ->required(static fn (Get $get): bool => $get('unrestricted') !== true)
                    ->rule(new QueryBuilder())
                    ->formatStateUsing(static fn (mixed $state): ?string => self::encodeFilters($state))
                    ->dehydrateStateUsing(static fn (mixed $state): ?array => self::decodeFilters($state)),
                Repeater::make('sort')
                    ->schema([
                        TextInput::make('property')
                            ->required(),
                        Select::make('direction')
                            ->options([
                                SortDirection::Asc->value => 'Ascending',
                                SortDirection::Desc->value => 'Descending',
                            ])
                            ->default(SortDirection::Asc->value)
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add sort')
                    ->reorderable(),
                TextInput::make('description')
                    ->maxLength(255),
                TextInput::make('priority')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(0)
                    ->required()
                    ->helperText('Higher priority ACLs are evaluated first.'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    /**
     * Render the stored filters as pretty printed JSON for the editor.
     */
    public static function encodeFilters(mixed $state): ?string
    {
        if ($state instanceof FiltersGroup) {
            $state = $state->toArray();
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (! is_array($decoded)) {
                return $state;
            }

            $state = $decoded;
        }

        if (! is_array($state)) {
            return null;
        }

        return json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Turn the editor content back into the array shape expected by the cast.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeFilters(mixed $state): ?array
    {
        if (is_array($state)) {
            return $state;
        }

        if (! is_string($state) || blank($state)) {
            return null;
        }

        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : null;
    }
}
