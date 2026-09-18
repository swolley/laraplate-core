<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;

/**
 * A model whose fields are defined by a dynamic entity and a preset rather than
 * by its own schema.
 *
 * Supplied by {@see \Modules\Core\Models\Concerns\HasDynamicContents}. The form
 * builder and the factory concern both take a class name and ask it these three
 * questions; the contract is what lets them say so, since a trait is not a type
 * and `class-string<Model&HasDynamicContents>` resolves to nothing.
 */
interface IDynamicContentModel
{
    /**
     * The entity type this model's contents are drawn from.
     */
    public static function getEntityType(): IDynamicEntityTypable;

    /**
     * @return Collection<int, Entity>
     */
    public static function fetchAvailableEntities(IDynamicEntityTypable $type): Collection;

    /**
     * @return Collection<int, Preset>
     */
    public static function fetchAvailablePresets(IDynamicEntityTypable $type): Collection;

    /**
     * The entity/preset pair this record's contents are built from.
     *
     * The related class is resolved per module through getPresettableClass(),
     * which returns the module's own subclass of Core's Presettable, so that is
     * what the relation is stated against.
     *
     * @return BelongsTo<Presettable, Model>
     */
    public function presettable(): BelongsTo;
}
