<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Schema\FieldDefinition;

/**
 * A module's contribution to another module's searchable document (M4a).
 *
 * Core owns the index and the base document/mapping of a searchable model; a
 * contributor registered for that model adds its own fields and mapping section
 * without Core ever referencing the contributing module. This is the generic
 * seam behind media AI enrichment (AI contributes to `Media`) and is reusable by
 * any Core searchable model.
 */
interface ISearchableContributor
{
    /**
     * FQCN of the searchable model this contributor augments.
     *
     * @return class-string<Model>
     */
    public function contributesTo(): string;

    /**
     * Document fields to merge into the model instance's searchable array. Base
     * document keys always win, so contributors add their own keys.
     *
     * @return array<string, mixed>
     */
    public function searchableFields(Model $model): array;

    /**
     * Index-mapping field definitions this contributor adds to the model's schema.
     *
     * @return list<FieldDefinition>
     */
    public function searchableMapping(): array;
}
