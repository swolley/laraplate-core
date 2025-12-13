<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final class AddContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add([
            'scope' => 'web',
            'locale' => App::getLocale(),
            'user' => Auth::user()?->id,
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }
}
