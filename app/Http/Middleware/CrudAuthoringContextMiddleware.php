<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the request as the authoring surface, so publication filters (validity)
 * are relaxed for staff working in the session app's CRUD. It is attached only to
 * the web `/app/crud/*` route group; the public API CRUD surface never sets it and
 * therefore keeps returning only currently-valid records.
 */
final class CrudAuthoringContextMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        authoring_surface(true);

        return $next($request);
    }
}
