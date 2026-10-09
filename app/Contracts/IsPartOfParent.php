<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model whose records only exist inside a parent record: translations, document lines, the
 * characteristics of a quality plan. Such a record has no visibility of its own: it inherits the parent's.
 *
 * When the generic CRUD read loads a relation, the related entity's `select` permission and ACL apply to it,
 * except for a part loaded from its own parent, which the parent's check already covers. A part reached from
 * any other model is checked against its parent's permission and ACL, through the relation named here.
 *
 * A model implements it when it has a non-nullable foreign key to its parent with cascade delete and no
 * Filament resource of its own. The rule depends neither on table names nor on which permissions exist: a
 * permission generated for the part's table is simply not consulted.
 */
interface IsPartOfParent
{
    /**
     * The name of the relation, on this model, that leads to the parent record.
     */
    public function parentRelation(): string;
}
