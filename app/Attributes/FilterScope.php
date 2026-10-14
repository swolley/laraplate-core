<?php

declare(strict_types=1);

namespace Modules\Core\Attributes;

use Attribute;

/**
 * Declares a local scope usable as a filter node (`{"scope": "<name>", "parameters": [...]}`).
 *
 * Put it on the scope method itself, either a `#[Scope]` method or a legacy `scopeXxx` method; a scope declared
 * in a trait is declared for every model using the trait. The name and the parameter types are read from the
 * method signature by {@see \Modules\Core\Support\ScopeGuard}, so the declaration cannot drift from the code.
 *
 * Only scopes that add where conditions qualify: one that joins, orders, selects, groups or limits is refused
 * when used even if declared.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FilterScope
{
    /**
     * @param  bool  $client  Whether CRUD requests may name the scope. False keeps it to ACLs, which administrators
     *                        write: a scope may read columns a client cannot filter on and would let it probe them.
     */
    public function __construct(
        public bool $client = false,
    ) {}
}
