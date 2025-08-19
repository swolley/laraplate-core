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
        // Use default guard instead of 'admin' guard to be compatible with Filament
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = Auth::user();

        // Check if user has admin role (adjust role name as needed)
        if (! $user || ! $user->hasRole(['superadmin', 'admin'])) {
            Auth::logout();

            return redirect()->route('filament.admin.auth.login');
        }

        return $next($request);
    }
}
