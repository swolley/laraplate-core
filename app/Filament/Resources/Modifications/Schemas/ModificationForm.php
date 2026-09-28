<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Schemas;

use Closure;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Models\Modification;

/**
 * Read-only view of a pending change: the panel votes on modifications, it never edits them.
 */
final class ModificationForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->disabled()
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('modifiable_type')
                            ->formatStateUsing(self::fromRecord('modifiable_type')),
                        TextInput::make('modifiable_id')
                            ->formatStateUsing(self::fromRecord('modifiable_id')),
                        TextInput::make('modifier_type')
                            ->formatStateUsing(self::fromRecord('modifier_type')),
                        TextInput::make('modifier_id')
                            ->formatStateUsing(self::fromRecord('modifier_id')),
                    ])
                    ->columnSpanFull(),
                Grid::make(4)
                    ->schema([
                        Toggle::make('active')
                            ->inline(false)
                            ->formatStateUsing(self::fromRecord('active')),
                        TextInput::make('operation')
                            ->formatStateUsing(self::fromRecord('operation'))
                            ->disabled(),
                        TextInput::make('approvers_required')
                            ->formatStateUsing(self::fromRecord('approvers_required')),
                        TextInput::make('disapprovers_required')
                            ->formatStateUsing(self::fromRecord('disapprovers_required')),
                    ])
                    ->columnSpanFull(),
                CodeEditor::make('modifications')
                    ->language(Language::Json)
                    ->formatStateUsing(static fn (?Modification $record): string => json_encode($record?->modifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Read the attribute from the record: most modification columns are in the model's `$hidden`
     * list, so they never reach the form state through serialization.
     */
    private static function fromRecord(string $attribute): Closure
    {
        return static fn (?Modification $record): mixed => $record?->getAttribute($attribute);
    }
}
