<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model whose appended attributes read a relation (the `cover` of a content reads its cover media).
 *
 * Without help each serialized row would load that relation on its own, one query per row. The generic CRUD read
 * adds the relations named here to the query as it adds the model's other default eager loads: once per result
 * set, narrowed by the related entity's permission and ACL on the request's guard, and left out when the caller may
 * not read the related entity. The appended attribute then reads the loaded relation, or answers null when the
 * relation was left out.
 */
interface IPreloadsAppendedRelations
{
    /**
     * The names of the relations the model's appended attributes read.
     *
     * @return list<string>
     */
    public function appendedAttributeRelations(): array;
}
