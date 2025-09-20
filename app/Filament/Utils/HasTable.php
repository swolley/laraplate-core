<?php

namespace Modules\Core\Filament\Utils;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
// use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException as GlobalInvalidArgumentException;
use LogicException;
use Modules\Cms\Casts\EntityType;
use Modules\Cms\Helpers\HasDynamicContents;
use Modules\Cms\Models\Preset;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Helpers\SortableTrait;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\User;
use PHPUnit\Event\InvalidArgumentException;
use Spatie\EloquentSortable\SortableTrait as BaseSortableTrait;

trait HasTable
{
    /**
     * 
     * @param ?callable(Collection<string,Column> $columns):void $columns 
     * @param ?callable(Collection<string,Action> $actions, Collection<string,BulkAction> $bulk_actions):void $actions 
     * @param ?callable(Collection<string,Filter> $default_filters):void $filters 
     * 
     * @throws LogicException 
     * @throws BindingResolutionException 
     * @throws InvalidArgumentException 
     * @throws GlobalInvalidArgumentException 
     */
    protected static function configureTable(Table $table, ?callable $columns = null, ?callable $actions = null, array $fixedActions = [], ?callable $filters = null): Table
    {
        /** @var User $user */
        $user = Auth::user();

        $model = $table->getModel();
        $model_instance = new $model();
        $model_table = $model_instance->getTable();
        $model_connection = $model_instance->getConnectionName() ?? 'default';
        $permissions_prefix = "$model_connection.$model_table";

        $traits = class_uses_recursive($model);
        $has_soft_deletes = in_array(SoftDeletes::class, $traits) || in_array(BaseSoftDeletes::class, $traits);
        $has_validity = in_array(HasValidity::class, $traits);
        $has_locks = in_array(HasLocks::class, $traits);
        $has_sorts = in_array(SortableTrait::class, $traits) || in_array(BaseSortableTrait::class, $traits);
        $has_dynamic_contents = in_array(HasDynamicContents::class, $traits);

        if ($has_soft_deletes) {
            $table->recordClasses(function ($record) {
                return $record->deleted_at ? [
                    'line-through' => true,
                    'text-gray-500' => true,
                ] : [];
            });
        }

        self::configureColumns(
            $table,
            $has_soft_deletes,
            $has_validity,
            $has_locks,
            $has_sorts,
            $has_dynamic_contents,
            $columns,
            $model_instance
        );

        self::configureActions(
            $table,
            $has_soft_deletes,
            $has_validity,
            $actions,
            $fixedActions,
            $permissions_prefix,
            $user,
            $model_instance
        );

        self::configureFilters(
            $table,
            $has_soft_deletes,
            $has_validity,
            $has_locks,
            $has_dynamic_contents,
            $filters,
            $model_instance,
            $permissions_prefix,
            $user
        );

        if ($has_sorts) {
            $table->reorderable('order_column');
        }

        if ($has_dynamic_contents) {
            $table->groups([
                Group::make('entity.name')
                    ->label('Entity'),
                Group::make('preset.name')
                    ->label('Preset'),
            ]);
        }

        return $table
            ->striped()
            ->deferLoading()
            ->deselectAllRecordsWhenFiltered()
            ->deferFilters()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginatedWhileReordering()
            ->reorderableColumns()
            ->deferColumnManager(true);
    }

    private static function configureColumns(
        Table $table,
        bool $hasSoftDeletes,
        bool $hasValidity,
        bool $hasLocks,
        bool $hasSorts,
        bool $hasDynamicContents,
        ?callable $columns,
        Model $model_instance
    ) {
        /** @var Collection<Column> $default_columns */
        $default_columns = collect([]);

        // if ($columns === null) {
        //     $inspected_data = Inspect::table($model_instance->getTable(), $model_instance->getConnectionName());
        //     foreach ($inspected_data->columns as $column) {
        //         // TODO: create default columns from table
        //     }
        // }

        if ($hasDynamicContents) {
            $default_columns->push(
                TextColumn::make('entity.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('preset.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            );
        }

        if ($hasValidity) {
            $default_columns->add(
                TextColumn::make('validity')
                    /** @var Model&HasValidity $record */
                    ->formatStateUsing(function (Model $record) {
                        return sprintf(
                            '<div class="space-y-1">
                            <div class="flex justify-between">
                                <span>Valid from:</span>
                                <span>%s</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Valid to:</span>
                                <span>%s</span>
                            </div>
                        </div>',
                            $record->{$record->validFromKey()}?->format('Y-m-d H:i:s'),
                            $record->{$record->validToKey()}?->format('Y-m-d H:i:s')
                        );
                    })
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->grow(false)
                    ->html()
            );
        }

        if ($hasLocks) {
            $default_columns->add(
                IconColumn::make('is_locked')
                    ->boolean()
                    ->alignCenter()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->tooltip(
                        function (Model $record) {
                            if (!$record->isLocked()) {
                                return null;
                            }

                            $locked_at = $record->{$record->getLockedAtColumn()};
                            if ($locked_at instanceof Carbon) {
                                $locked_at = $locked_at->format('Y-m-d H:i:s');
                            }

                            $locked_by = $record->{$record->getLockedByColumn()};

                            return sprintf('Locked at %s by User #%s', $locked_at, $locked_by);
                        }
                    )
                    ->falseIcon(false)
            );
        }

        if ($hasSorts) {
            $default_columns->add(
                TextColumn::make('order_column')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->grow(false)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true)
            );
        }

        if ($model_instance->timestamps) {
            $default_columns->add(
                TextColumn::make('timestamps')
                    ->formatStateUsing(function (Model $record) use ($hasSoftDeletes) {
                        $string =
                            '<div class="space-y-1">
                            <div class="flex justify-between">
                                <span>Created:</span>
                                <span>%s</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Updated:</span>
                                <span>%s</span>
                            </div>';
                        $values = [
                            $record->{$record->getCreatedAtColumn() ?? 'created_at'}->format('Y-m-d H:i:s'),
                            $record->{$record->getUpdatedAtColumn() ?? 'updated_at'}->format('Y-m-d H:i:s')
                        ];
                        if ($hasSoftDeletes) {
                            $string .=
                                '<div class="flex justify-between">
                                <span>Deleted:</span>
                                <span>%s</span>
                            </div>';
                            $values[] = $record->{$record->getDeletedAtColumn() ?? 'deleted_at'}?->format('Y-m-d H:i:s');
                        }
                        $string .= '</div>';

                        return sprintf($string, ...$values);
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->grow(false)
                    ->html()
            );
        }

        if ($columns !== null) {
            $columns($default_columns);
        }

        $default_columns->keyBy(fn(Column $column) => $column->getName());
        $primary_key = $model_instance->getKeyName();
        if (!$default_columns->offsetExists($primary_key)) {
            $default_columns->unshift(
                TextColumn::make('id')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
            );
        }
        $default_columns->each(function (Column $column) {
            if ($column->isSearchable()) {
                $column->searchable(isIndividual: true);
            }
        });
        $table->columns($default_columns->all());
    }

    private static function configureActions(
        Table $table,
        bool $hasSoftDeletes,
        bool $hasValidity,
        ?callable $actions,
        array $fixedActions,
        string $permissionsPrefix,
        User $user
    ) {
        /** @var Collection<Action> $default_actions */
        $default_actions = collect([
            ViewAction::make()->hiddenLabel(),
        ]);
        /** @var Collection<BulkAction> $default_bulk_actions */
        $default_bulk_actions = collect([]);

        if ($hasValidity) {
            $default_actions->push(
                Action::make('publish')
                    ->hiddenLabel()
                    ->icon(Heroicon::OutlinedPlay)
                    ->action(function (Model $record) {
                        $valid_from_column = $record::validFromKey();
                        $valid_to_column = $record::validToKey();
                        $record->update([$valid_from_column => now(), $valid_to_column => null]);
                        $record->refresh();
                    })
                    ->disabled(fn(Model $record) => $record->isValid())
                    ->color(fn(Model $record) => $record->isValid() ? 'gray' : 'success')
                    ->requiresConfirmation(),
                Action::make('unpublish')
                    ->hiddenLabel()
                    ->icon(Heroicon::OutlinedStop)
                    ->color(fn(Model $record) => $record->isDraft() ? 'gray' : 'warning')
                    ->disabled(fn(Model $record) => $record->isDraft())
                    ->action(function (Model $record) {
                        $valid_to_column = $record::validToKey();
                        $record->update([$valid_to_column => now()]);
                        $record->refresh();
                    })
                    ->requiresConfirmation()
            );
        }

        if ($user->can("$permissionsPrefix.update")) {
            $default_actions->add(
                EditAction::make()
                    ->hiddenLabel()
            );
        }

        if ($hasSoftDeletes) {
            if ($user->can("$permissionsPrefix.restore")) {
                $default_actions->add(
                    RestoreAction::make()
                        ->hiddenLabel()
                );
                $default_bulk_actions->add(
                    RestoreBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                );
            }
            if ($user->can("$permissionsPrefix.delete")) {
                $default_actions->add(
                    DeleteAction::make()
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->hiddenLabel()
                );
                $default_bulk_actions->add(
                    DeleteBulkAction::make()
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->deselectRecordsAfterCompletion()
                );
            }
        }

        if ($user->can("$permissionsPrefix.forceDelete")) {
            $default_actions->add(
                ForceDeleteAction::make()
                    ->visible(true)
                    ->requiresConfirmation()
                    ->hiddenLabel()
            );
            $default_bulk_actions->add(
                ForceDeleteBulkAction::make()
                    ->visible(true)
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
            );
        }

        if ($actions) {
            $actions($default_actions);
        }

        $fixed_actions_list = [];
        $grouped_actions_list = [];
        $default_actions->keyBy(fn(Action $action) => $action->getName());
        foreach ($default_actions as $name => $action) {
            if ($fixedActions !== [] && in_array($name, $fixedActions)) {
                $fixed_actions_list[] = $action;
            } else {
                $grouped_actions_list[] = $action;
            }
        }
        if ($grouped_actions_list !== []) {
            $fixed_actions_list[] = ActionGroup::make($grouped_actions_list);
        }
        $table->recordActions($fixed_actions_list);

        if ($default_bulk_actions->isNotEmpty()) {
            $table->toolbarActions([
                BulkActionGroup::make($default_bulk_actions->all()),
            ]);
        }
    }

    private static function configureFilters(
        Table $table,
        bool $hasSoftDeletes,
        bool $hasValidity,
        bool $hasLocks,
        bool $hasDynamicContents,
        ?callable $filters,
        Model $model_instance,
        string $permissionsPrefix,
        User $user
    ) {
        $default_filters = collect([]);

        if ($hasDynamicContents) {
            $entity_type = EntityType::tryFrom($model_instance->getTable());
            if ($entity_type) {
                $default_filters->add(
                    SelectFilter::make('preset')
                        ->label('Preset')
                        ->multiple()
                        ->options(function () use ($entity_type) {
                            return \Modules\Cms\Models\Preset::query()
                                ->join('entities', 'presets.entity_id', '=', 'entities.id')
                                ->where('presets.is_active', true)
                                ->whereHas('entity', fn(Builder $query) => $query->where([
                                    'entities.is_active' => true,
                                    'entities.type' => $entity_type,
                                ]))
                                ->orderBy('entities.name')
                                ->orderBy('presets.name')
                                ->get(['presets.id', 'presets.name', 'presets.entity_id', 'entities.name'])
                                ->mapWithKeys(function (Preset $preset) {
                                    return [$preset->id => $preset->entity->name . ' - ' . $preset->name];
                                });
                        })
                        ->query(function (Builder $query, array $data): Builder {
                            return $query->when(
                                $data['values'],
                                fn(Builder $query, $values): Builder => $query->whereIn('preset_id', $values)
                            );
                        }),
                );
            }
        }

        // FILTERS
        if ($hasSoftDeletes && $user->can("$permissionsPrefix.restore")) {
            $deleted_at_column = $model_instance->getDeletedAtColumn();
            $default_filters->push(
                // TrashedFilter::make(),
                SelectFilter::make($deleted_at_column)
                    ->label('Deleted at')
                    ->attribute($deleted_at_column)
                    ->options([
                        // 'all' => 'All',
                        'none' => 'Without Trashed',
                        'only' => 'Only Trashed',
                        'today' => 'Today',
                        'week' => 'Week',
                        'month' => 'Month',
                        'year' => 'Year',
                    ])
                    ->query(function (Builder $query, array $data) use ($deleted_at_column): Builder {
                        return $query->when($data['value'], function (Builder $query, $value) use ($deleted_at_column): Builder {
                            return match ($value) {
                                // 'all' => $query->withTrashed(),
                                'none' => $query->withoutTrashed(),
                                'only' => $query->onlyTrashed(),
                                'today' => $query->onlyTrashed()->whereDate($deleted_at_column, '>=', now()->startOfDay()),
                                'week' => $query->onlyTrashed()->whereDate($deleted_at_column, '>=', now()->startOfWeek()),
                                'month' => $query->onlyTrashed()->whereDate($deleted_at_column, '>=', now()->startOfMonth()),
                                'year' => $query->onlyTrashed()->whereDate($deleted_at_column, '>=', now()->startOfYear()),
                                default => $query->withoutGlobalScope('deleted'),
                            };
                        });
                    }),
            );
        }
        if ($hasLocks) {
            $locked_at_column = $model_instance->getLockedAtColumn();
            $default_filters->push(
                // TernaryFilter::make('is_locked')
                //     ->label('Locked')
                //     ->attribute('is_locked')
                //     ->nullable()
                SelectFilter::make($locked_at_column)
                    ->label('Locked at')
                    ->attribute($locked_at_column)
                    ->options([
                        // 'all' => 'All',
                        'none' => 'Without Locked',
                        'only' => 'Only Locked',
                        'today' => 'Today',
                        'week' => 'Week',
                        'month' => 'Month',
                        'year' => 'Year',
                    ])
                    ->query(function (Builder $query, array $data) use ($locked_at_column): Builder {
                        return $query->when($data['value'], function (Builder $query, $value) use ($locked_at_column): Builder {
                            return match ($value) {
                                // 'all' => $query->withLocked(),
                                'none' => $query->withoutLocked(),
                                'only' => $query->onlyLocked(),
                                'today' => $query->onlyLocked()->whereDate($locked_at_column, '<=', now()->startOfDay()),
                                'week' => $query->onlyLocked()->whereDate($locked_at_column, '<=', now()->startOfWeek()),
                                'month' => $query->onlyLocked()->whereDate($locked_at_column, '<=', now()->startOfMonth()),
                                'year' => $query->onlyLocked()->whereDate($locked_at_column, '<=', now()->startOfYear()),
                                default => $query,
                            };
                        });
                    }),
            );
        }
        if ($hasValidity) {
            $default_filters->add(
                SelectFilter::make('is_valid')
                    ->label('Valid')
                    ->options([
                        // 'all' => 'All',
                        'valid' => 'Valid',
                        'scheduled' => 'Scheduled',
                        'expiring' => 'Expiring',
                        'expired' => 'Expired',
                        'draft' => 'Draft',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            // 'all' => $query,
                            'valid' => $query->valid(),
                            'scheduled' => $query->scheduled(),
                            'expiring' => $query->expiring(),
                            'expired' => $query->expired(),
                            'draft' => $query->draft(),
                            default => $query,
                        };
                    }),
            );
        }

        if ($model_instance->timestamps) {
            $created_at_column = $model_instance->getCreatedAtColumn();
            $updated_at_column = $model_instance->getUpdatedAtColumn();
            $default_filters->push(
                SelectFilter::make($created_at_column)
                    ->label('Created at')
                    ->attribute($created_at_column)
                    ->options([
                        // 'all' => 'All',
                        'today' => 'Today',
                        'week' => 'Week',
                        'month' => 'Month',
                        'year' => 'Year',
                    ])
                    ->query(function (Builder $query, array $data) use ($created_at_column): Builder {
                        return $query->when($data['value'], function (Builder $query, $value) use ($created_at_column): Builder {
                            return match ($value) {
                                // 'all' => $query,
                                'today' => $query->whereDate($created_at_column, '>=', now()->startOfDay()),
                                'week' => $query->whereDate($created_at_column, '>=', now()->startOfWeek()),
                                'month' => $query->whereDate($created_at_column, '>=', now()->startOfMonth()),
                                'year' => $query->whereDate($created_at_column, '>=', now()->startOfYear()),
                                default => $query,
                            };
                        });
                    }),
                SelectFilter::make($updated_at_column)
                    ->label('Updated at')
                    ->attribute($updated_at_column)
                    ->options([
                        // 'all' => 'All',
                        'today' => 'Today',
                        'week' => 'Week',
                        'month' => 'Month',
                        'year' => 'Year',
                    ])
                    ->query(function (Builder $query, array $data) use ($updated_at_column): Builder {
                        return $query->when($data['value'], function (Builder $query, $value) use ($updated_at_column): Builder {
                            return match ($value) {
                                // 'all' => $query,
                                'today' => $query->whereDate($updated_at_column, '>=', now()->startOfDay()),
                                'week' => $query->whereDate($updated_at_column, '>=', now()->startOfWeek()),
                                'month' => $query->whereDate($updated_at_column, '>=', now()->startOfMonth()),
                                'year' => $query->whereDate($updated_at_column, '>=', now()->startOfYear()),
                                default => $query,
                            };
                        });
                    }),
            );
        }

        if ($filters) {
            $filters($default_filters);
        }

        $table->filters($default_filters->all()/*, layout: FiltersLayout::Modal*/);
    }
}
