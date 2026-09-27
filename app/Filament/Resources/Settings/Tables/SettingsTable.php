<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Tables;

use function Filament\Support\generate_icon_html;

use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\View\Components\Columns\IconColumnComponent\IconComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Setting;

final class SettingsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('group_name')
                        ->searchable()
                        ->sortable()
                        ->hidden(),
                    TextColumn::make('module')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('description')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('type')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('value')
                        ->alignCenter()
                        ->html()
                        ->badge(static fn (Setting $record): bool => self::isFlatList($record->value))
                        ->state(static fn (Setting $record): mixed => match ($record->type) {
                            SettingTypeEnum::Boolean => generate_icon_html(
                                $record->value ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedMinusCircle,
                                attributes: (new FilamentComponentAttributeBag)
                                    ->color(IconComponent::class, $record->value ? 'success' : 'gray'),
                                size: IconSize::Large,
                            ),
                            default => $record->value,
                        }),
                    IconColumn::make('is_public')
                        ->label('Public')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->trueColor('success')
                        ->falseIcon(false)
                        ->alignCenter()
                        ->toggleable(),
                    IconColumn::make('is_internal')
                        ->label('Internal')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->trueColor('success')
                        ->falseIcon(false)
                        ->alignCenter()
                        ->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('encrypted')
                        ->label('Encrypted')
                        ->boolean()
                        ->alignCenter()
                        ->trueIcon('heroicon-o-key')
                        ->falseIcon(false)
                        ->toggleable(isToggledHiddenByDefault: false),
                ]);
            },
            filters: static function (Collection $default_filters): void {
                $default_filters->unshift(...[
                    SelectFilter::make('type')
                        ->options([
                            'string' => 'String',
                            'integer' => 'Integer',
                            'float' => 'Float',
                            'boolean' => 'Boolean',
                            'array' => 'Array',
                            'json' => 'JSON',
                            'date' => 'Date',
                            'datetime' => 'DateTime',
                        ]),
                    SelectFilter::make('group_name')
                        ->options(static fn (): array => self::cachedGroupNameOptions()),
                    SelectFilter::make('module')
                        ->options(static fn (): array => self::cachedModuleOptions()),
                    SelectFilter::make('is_public')
                        ->label('Public')
                        ->searchable()
                        ->options([
                            '1' => 'Public',
                            '0' => 'Private',
                        ]),
                    SelectFilter::make('is_internal')
                        ->label('Internal')
                        ->searchable()
                        ->options([
                            '1' => 'Internal',
                            '0' => 'Not Internal',
                        ]),
                    SelectFilter::make('encrypted')
                        ->options([
                            '1' => 'Encrypted',
                            '0' => 'Not Encrypted',
                        ]),
                ]);
            },
        )
            // ->defaultGroup(
            //     Group::make('group_name')->label('Group Name'),
            // )
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('group_name')
                ->orderBy('name'))
            ->defaultGroup('group_name');
    }

    /**
     * Whether the value is a non-empty list of scalars, rendered as one badge per item.
     */
    private static function isFlatList(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return false;
        }

        return array_all($value, static fn (mixed $item): bool => is_scalar($item));
    }

    /**
     * @return array<string, string>
     */
    private static function cachedGroupNameOptions(): array
    {
        $ttl = config('core.filament.tabs_counts_ttl_seconds', 300);

        return Cache::remember(
            'filament_settings_distinct_group_name',
            $ttl,
            static fn (): array => SettingResource::getEloquentQuery()
                ->select('group_name')
                ->distinct()
                ->orderBy('group_name')
                ->pluck('group_name', 'group_name')
                ->toArray(),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function cachedModuleOptions(): array
    {
        $ttl = config('core.filament.tabs_counts_ttl_seconds', 300);

        return Cache::remember(
            'filament_settings_distinct_module',
            $ttl,
            static fn (): array => SettingResource::getEloquentQuery()
                ->select('module')
                ->whereNotNull('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module', 'module')
                ->toArray(),
        );
    }
}
