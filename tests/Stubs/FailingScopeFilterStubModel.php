<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Attributes\FilterScope;
use RuntimeException;

/**
 * A model with a client scope that throws an exception of its own, for the client-side scope checks: the scope's
 * message names an internal detail and its parameter, neither of which may reach a client.
 */
class FailingScopeFilterStubModel extends Model
{
    protected $table = 'scope_filter_stub';

    /**
     * @param  Builder<static>  $query
     *
     * @throws RuntimeException always
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function failing(Builder $query, string $label): void
    {
        throw new RuntimeException('internal detail of ' . $label);
    }
}
