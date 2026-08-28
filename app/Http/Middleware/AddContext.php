<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches the request identity to the log context.
 *
 * The surface is passed in at registration (`AddContext:api`) rather than derived here:
 * the three surfaces are route groups, so the router already knows which one is serving
 * the request, and reading it back from the path would duplicate configuration that
 * `AdminPanelProvider` and the route files own.
 */
final class AddContext
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $scope  Which HTTP surface is serving this request: app, api or admin.
     */
    public function handle(Request $request, Closure $next, string $scope = 'app'): Response
    {
        Context::add([
            'scope' => $scope,
            'locale' => App::getLocale(),
            'user' => Auth::user()?->id,
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }
}
