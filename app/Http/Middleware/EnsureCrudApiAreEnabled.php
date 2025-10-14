<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnsureCrudApiAreEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('core.expose_crud_api'), 403, 'Forbidden');

        return $next($request);
    }
}
