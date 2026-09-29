<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A module's contribution to another module's Filament resource surface (M22).
 *
 * The UI twin of {@see \Modules\Core\Search\Contracts\ISearchableContributor}:
 * Core owns a resource's base schema, and a contributor registered for a model
 * adds its own read-only infolist sections and record actions without Core ever
 * referencing the contributing module. This is the seam behind the media AI
 * analysis panel (AI contributes to `Media`) and is reusable by any Core surface.
 */
interface IResourceSchemaContributor
{
    /**
     * FQCN of the model whose Filament surface this contributor augments.
     *
     * @return class-string<Model>
     */
    public function contributesTo(): string;

    /**
     * Filament infolist/schema components (e.g. a read-only Section) to append to
     * the target record's view surface.
     *
     * @return list<mixed>
     */
    public function infolistSections(Model $record): array;

    /**
     * Filament record actions to append to the target record's surface.
     *
     * @return list<mixed>
     */
    public function recordActions(Model $record): array;
}
