<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model that carries personal attributes the anonymous user must never receive: the author of a comment or
 * a rating, the contact data of a contributor. Staff, authenticated callers and Filament see the record as ever.
 *
 * The attributes are removed when the model is serialized for the anonymous user (see
 * {@see \Modules\Core\Models\Concerns\HidesAttributesFromAnonymous}), so a record is clean on every route that
 * returns it, as an entity or as a relation of another entity.
 */
interface IHidesAttributesFromAnonymous
{
    /**
     * The attributes, and the relations, never returned to the anonymous user.
     *
     * Only what the model's own serialization produces is removed. Fields that the translations and dynamic-content
     * traits merge into the array afterwards (the translatable fields, the dynamic ones a preset defines) are added
     * back by those traits' `toArray()` and are not covered: do not declare them here. Declare a column or a
     * relation of the model itself.
     *
     * @return list<string>
     */
    public function anonymousHiddenAttributes(): array;
}
