<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\CMS\Models\Comment;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Modification;

final class ModificationsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('modifiable_id')
                        ->numeric()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('modifiable_type')
                        ->searchable(),
                    TextColumn::make('modifier.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('modifications.original')
                        ->label('Original')
                        ->limit(50),
                    TextColumn::make('modifications.modified')
                        ->label('Modified')
                        ->limit(50),
                    TextColumn::make('meta')
                        ->label('Meta')
                        ->badge()
                        ->getStateUsing(function (Modification $record): ?string {
                            $meta = $record->latestAutomatedVoteMeta();

                            $string = '';

                            foreach ($meta as $key => $value) {
                                $string .= $key . ': ' . $value . '<br>';
                            }

                            return $string;
                        })
                        ->color(fn (?string $state): string => match ($state) {
                            'requires_human_review' => 'warning',
                            'processing', 'queued' => 'gray',
                            'auto_approved' => 'success',
                            'auto_rejected' => 'danger',
                            default => 'gray',
                        })
                        ->visible(fn (Modification $record): bool => $record->modifiable_type === Comment::class)
                        ->html(),
                    TextColumn::make('disapprovers_required')
                        ->label('Disapprovals required')
                        ->numeric()
                        ->visible(fn (Modification $record): bool => $record->modifiable_type === Comment::class),
                ]);
            },
        )
            ->defaultGroup(
                Group::make('modifiable_type')
                    ->label('Modifiable Type'),
            );
    }
}
