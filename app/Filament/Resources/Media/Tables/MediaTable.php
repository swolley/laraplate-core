<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Media;

/**
 * The gallery listing (M22): a filterable, read-only table of claimed media. The
 * default view action (from {@see HasTable}) opens the read-only infolist; no
 * create/edit action is offered.
 */
final class MediaTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable()
                        ->limit(40),
                    TextColumn::make('mime_type')
                        ->label('Type')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('collection_name')
                        ->label('Collection')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('owner')
                        ->state(static fn (Media $record): string => $record->model_type !== ''
                            ? class_basename($record->model_type) . ' #' . $record->model_id
                            : '—'),
                    TextColumn::make('size')
                        ->formatStateUsing(static fn (int $state): string => Number::fileSize($state))
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable(),
                );
            },
            filters: static function (Collection $default_filters): void {
                $default_filters->push(
                    SelectFilter::make('mime_type')
                        ->label('Type')
                        ->options(static fn (): array => self::distinctOptions('mime_type')),
                    SelectFilter::make('collection_name')
                        ->label('Collection')
                        ->options(static fn (): array => self::distinctOptions('collection_name')),
                    SelectFilter::make('model_type')
                        ->label('Owner')
                        ->options(static fn (): array => self::ownerOptions()),
                );
            },
        );
    }

    /**
     * Distinct non-empty string values of a media column, as `value => value`.
     *
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        $options = [];

        foreach (Media::query()->distinct()->orderBy($column)->pluck($column) as $value) {
            if (is_string($value) && $value !== '') {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    /**
     * Distinct owner morph types, labelled by their short class name.
     *
     * @return array<string, string>
     */
    private static function ownerOptions(): array
    {
        $options = [];

        foreach (Media::query()->distinct()->whereNotNull('model_type')->pluck('model_type') as $type) {
            if (is_string($type) && $type !== '') {
                $options[$type] = class_basename($type);
            }
        }

        return $options;
    }
}
