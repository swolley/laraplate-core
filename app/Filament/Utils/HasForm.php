<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Filament\FilamentTraitResolver;
use Modules\Core\Models\Concerns\HasDynamicContents;
use Modules\Core\Models\Preset;

trait HasForm
{
    /**
     * Wire shared form behaviour. Call as:
     * `return self::configureForm($schema->components([...]));`
     *
     * When the schema model uses HasDynamicContents, prepends Entity → Preset
     * UI (dehydrated) and a required hidden presettable_id resolved from the
     * active presettable. Legacy `self::configureForm($schema)` before
     * `->components()` is a no-op for injection.
     */
    protected static function configureForm(Schema $schema): Schema
    {
        $model_class = $schema->getModel();

        if ($model_class === null || ! class_uses_trait($model_class, HasDynamicContents::class)) {
            return $schema;
        }

        /** @var class-string<Model&HasDynamicContents> $model_class */
        $entity_type = $model_class::getEntityType();

        if (! $entity_type instanceof IDynamicEntityTypable) {
            return $schema;
        }

        $owned = FilamentTraitResolver::formColumnsOwnedByHasForm($model_class);
        $existing = array_values(array_filter(
            $schema->getComponents(withActions: true, withHidden: true),
            static function (mixed $component) use ($owned): bool {
                if (! is_object($component) || ! method_exists($component, 'getName')) {
                    return true;
                }

                return ! in_array($component->getName(), $owned, true);
            },
        ));

        return $schema->components([
            ...self::dynamicEntityPresetComponents($model_class, $entity_type),
            ...$existing,
        ]);
    }

    /**
     * @param  class-string<Model&HasDynamicContents>  $model_class
     * @return list<Component>
     */
    private static function dynamicEntityPresetComponents(string $model_class, IDynamicEntityTypable $entity_type): array
    {
        return [
            Select::make('dynamic_entity_id')
                ->label('Entity')
                ->options(static fn (): array => $model_class::fetchAvailableEntities($entity_type)
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(static function (Select $component, mixed $state): void {
                    if (filled($state)) {
                        return;
                    }

                    $record = $component->getRecord();

                    if (! $record instanceof Model) {
                        return;
                    }

                    $presettable = $record->getRelationValue('presettable') ?? $record->presettable()->first();

                    if ($presettable !== null) {
                        $component->state($presettable->getAttribute('entity_id'));
                    }
                })
                ->afterStateUpdated(static function (Set $set): void {
                    $set('dynamic_preset_id', null);
                    $set('presettable_id', null);
                }),
            Select::make('dynamic_preset_id')
                ->label('Preset')
                ->options(static function (Get $get) use ($model_class, $entity_type): array {
                    $entity_id = $get('dynamic_entity_id');

                    if (blank($entity_id)) {
                        return [];
                    }

                    return $model_class::fetchAvailablePresets($entity_type)
                        ->where('entity_id', (int) $entity_id)
                        ->pluck('name', 'id')
                        ->all();
                })
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->dehydrated(false)
                ->disabled(static fn (Get $get): bool => blank($get('dynamic_entity_id')))
                ->afterStateHydrated(static function (Select $component, mixed $state): void {
                    if (filled($state)) {
                        return;
                    }

                    $record = $component->getRecord();

                    if (! $record instanceof Model) {
                        return;
                    }

                    $presettable = $record->getRelationValue('presettable') ?? $record->presettable()->first();

                    if ($presettable !== null) {
                        $component->state($presettable->getAttribute('preset_id'));
                    }
                })
                ->afterStateUpdated(static function (Set $set, Get $get, mixed $state) use ($model_class, $entity_type): void {
                    if (blank($state)) {
                        $set('presettable_id', null);

                        return;
                    }

                    $preset = $model_class::fetchAvailablePresets($entity_type)
                        ->firstWhere('id', (int) $state);

                    if (! $preset instanceof Preset) {
                        $set('presettable_id', null);

                        return;
                    }

                    $active = $preset->activePresettable();
                    $set('presettable_id', $active?->getKey());
                }),
            Hidden::make('presettable_id')
                ->required(),
        ];
    }
}
