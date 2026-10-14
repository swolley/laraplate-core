<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model that presents the attributes of a related record as its own: a product reads and writes the
 * title of the content it extends as if it were a column of the product.
 *
 * The CRUD engine uses it to filter on such an attribute by its plain name: a property the model does not own is
 * the same filter on the mounted relation, which resolves it there (a column, a translated field or a dynamic
 * field of the related model). How the attribute reaches the serialized record is the model's own `toArray()`.
 */
interface IMountsRelatedAttributes
{
    /**
     * The name of the relation whose attributes are mounted on the model.
     */
    public function mountedRelation(): string;

    /**
     * Whether `$key` is read from the mounted relation, that is, the model does not own it (a column, a
     * mutator, a method or a relation of its own).
     */
    public function isMountedAttribute(string $key): bool;
}
