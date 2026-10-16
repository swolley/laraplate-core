<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Helpers\LocaleContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the backoffice a content language of its own, apart from the language of the interface: the one
 * picked in this session, or the default locale. Translated records are read and written in it, and every
 * record stays visible whether it has that translation or not.
 *
 * Registered as persistent panel middleware, so Livewire updates (form saves) get it too.
 */
final class ApplyBackofficeContentLocale
{
    public const string SESSION_KEY = 'backoffice.content_locale';

    /**
     * @return list<string>
     */
    public static function availableLocales(): array
    {
        $configured = config('app.available_locales', translations());

        return array_values(array_filter(
            is_array($configured) ? $configured : [],
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        ));
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $picked = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        LocaleContext::setContentLocale(
            is_string($picked) && in_array($picked, self::availableLocales(), true) ? $picked : LocaleContext::getDefault(),
        );

        return $next($request);
    }
}
