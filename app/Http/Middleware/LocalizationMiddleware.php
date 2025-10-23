<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->lang !== App::getLocale()) {
            App::setLocale($user->lang);
        } elseif (! $user) {
            $lang = $request->getPreferredLanguage();

            if (! in_array($lang, [null, '', '0'], true)) {
                $lang = Str::of($lang)->before('_')->value();
                App::setLocale($lang);
            }
        }

        return $next($request);
    }
}
