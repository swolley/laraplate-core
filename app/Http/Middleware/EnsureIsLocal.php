<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class EnsureIsLocal
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless(App::isLocal(), 401, 'Unauthorized');

        return $next($request);
    }
}
