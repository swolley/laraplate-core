<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Marks an `/api` route that authenticates its caller itself, typically with a secret the caller
 * sends as `Authorization: Bearer` (payment and e-invoice provider callbacks, machine sources).
 *
 * The marker does nothing at run time: {@see AuthenticateApiRequest} looks for it in the route's
 * middleware and then keeps the switch, the `api` guard and the rate limit but leaves the bearer
 * alone, so the route's own check sees it. Declare it as
 * `->middleware(SelfAuthenticatedApiRoute::class)`; a route without it treats every bearer as a
 * Laraplate token.
 */
final class SelfAuthenticatedApiRoute
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
