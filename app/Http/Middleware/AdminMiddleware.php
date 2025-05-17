<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = Auth::guard('admin')->user();

        if (! $user || ! $user->hasRole('superadmin')) {
            Auth::guard('admin')->logout();

            return redirect()->route('filament.admin.auth.login');
        }

        return $next($request);
    }
}
