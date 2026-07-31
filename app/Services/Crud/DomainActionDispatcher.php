<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Modules\Core\Models\User;

/**
 * Resolves a domain action, authorizes it, then invokes it.
 *
 * Authorization goes through the Gate rather than a bare permission check: a
 * domain action's guard is intrinsic to it — posting an already-posted invoice
 * is not a permission problem — and the module policies already combine the
 * state predicate with the permission.
 *
 * Resolution happens before authorization on purpose. An action nobody
 * registered is a 404; answering 403 would claim it exists.
 */
final class DomainActionDispatcher
{
    public function __construct(private readonly DomainActionRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Model $record, string $action, User $user, array $payload = []): mixed
    {
        $handler = $this->registry->resolve($record::class, $action);

        throw_if(
            $handler === null,
            ModelNotFoundException::class,
            sprintf('No domain action [%s] is registered for [%s].', $action, $record::class),
        );

        throw_unless(
            $user->can(self::policyMethodFor($action), $record),
            AuthorizationException::class,
            'User not allowed to perform this action',
        );

        return $handler($record, $payload, $user);
    }

    /**
     * Actions are registered and seeded in snake_case; the matching policy
     * method is camelCase, so `force_post` authorizes against `forcePost`.
     * Names already in camelCase pass through unchanged.
     */
    private static function policyMethodFor(string $action): string
    {
        return Str::camel($action);
    }
}
