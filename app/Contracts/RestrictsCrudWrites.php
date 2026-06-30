<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model whose write operations must not be performed through the generic CRUD layer.
 *
 * Implementers are typically immutable or service-derived models (e.g. posted accounting
 * vouchers, stock movements) that must only be mutated through their owning service, which
 * enforces domain invariants the generic CRUD cannot.
 */
interface RestrictsCrudWrites
{
    /**
     * Generic CRUD write operations denied for this model.
     *
     * @return list<string> subset of "insert", "update", "delete", "forceDelete", "restore", "approve", "disapprove", "lock", "unlock"
     */
    public function deniedCrudWrites(): array;
}
