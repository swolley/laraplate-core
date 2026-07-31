<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that redefines one of Core's generic CRUD verbs.
 *
 * Core's generic verbs act on structures attached to a record — a pending
 * Modification, the lock columns, the soft-delete state. A module may need the
 * same verb to act on the record itself: `ReturnOrder::approve` advances a
 * document lifecycle rather than voting on a pending edit.
 *
 * Declaring it here is what lets the dispatcher pick the module implementation,
 * and what lets {@see \Modules\Core\Services\Crud\DomainActionRegistry} reject
 * the combination that would make one verb mean two things at once.
 */
interface OverridesGenericCrudActions
{
    /**
     * @return list<string>
     */
    public static function overriddenCrudActions(): array;
}
