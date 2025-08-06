<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App;
use Closure;
use Illuminate\Http\Request;

final class EnsureCrudApiAreEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! config('core.expose_crud_api')) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
