<?php

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('modifiable_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('modifiable_type')
                    ->searchable(),
                TextColumn::make('modifier_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('modifier_type')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                IconColumn::make('is_update')
                    ->boolean(),
                TextColumn::make('approvers_required')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('disapprovers_required')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('md5')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
