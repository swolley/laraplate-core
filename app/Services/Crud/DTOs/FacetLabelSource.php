<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\DTOs;

/**
 * A DB-materialized label source for an open facet, declared by a model when the
 * label lives on a related table reachable by a foreign key but no {@see
 * \Illuminate\Database\Eloquent\Relations\BelongsTo} relation is defined for it
 * (e.g. the key is exposed only through an accessor). It lets the facet resolve,
 * search and sort labels through the foreign key alone, exactly as it would for a
 * real single-hop BelongsTo.
 *
 * The alias under which a model registers this source (see {@see
 * \Modules\Core\Contracts\ProvidesFacetLabelSources}) is the `relation` segment a
 * facet's `fields`/`labelField` refer to: registering `entity` mapping to
 * `entity_id` makes `entity.name` resolvable for a facet grouped by `entity_id`.
 */
final readonly class FacetLabelSource
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $relatedClass  The model owning the label column.
     * @param  string  $foreignKey  The facet group key (foreign key column on the grouped model) this label is keyed by.
     * @param  string  $ownerKey  The related model column matched to the foreign key.
     * @param  ?string  $translationRelation  A HasMany translation relation on the related model (e.g. `translations`)
     *                                        whose locale-scoped row carries the label; null for a direct column.
     * @param  ?string  $translationColumn  The label column on the translation row (e.g. `name`), required when
     *                                       `$translationRelation` is set.
     */
    public function __construct(
        public string $relatedClass,
        public string $foreignKey,
        public string $ownerKey = 'id',
        public ?string $translationRelation = null,
        public ?string $translationColumn = null,
    ) {}
}
