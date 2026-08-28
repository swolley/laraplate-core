<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arms the approvals preview flag for the current request.
 *
 * Two storage modes, selected at registration:
 * - `request` (app / api): `?preview=true` enables preview for this call only. A
 *   missing param is off. The session is never read or written — the SPA owns
 *   whether to keep sending the param.
 * - `session` (admin): the param toggles a session flag that survives across
 *   Livewire updates until `preview=false` is sent.
 *
 * Livewire updates for the admin panel still travel through the `web` group, which
 * carries the request-scoped registration. Those posts are skipped here so a
 * session-backed admin toggle is not wiped by the absence of a query param.
 */
final class PreviewMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $storage  `request` or `session`
     */
    public function handle(Request $request, Closure $next, string $storage = 'request'): Response
    {
        if ($storage === 'request' && $this->isLivewireUpdate()) {
            return $next($request);
        }

        $request->attributes->set('_preview_storage', $storage);

        if ($storage === 'request') {
            // Explicit false when the param is absent: never inherit a leftover session.
            $enabled = $request->has('preview')
                && filter_var($request->input('preview'), FILTER_VALIDATE_BOOLEAN);

            $request->attributes->set('preview', $enabled);
        } elseif ($request->has('preview')) {
            preview(filter_var($request->input('preview'), FILTER_VALIDATE_BOOLEAN));
        } else {
            // Session mode with no param: expose the persisted value on this request
            // so preview() readers do not have to know about the storage mode.
            $request->attributes->set('preview', (bool) session('preview', false));
        }

        return $next($request);
    }

    private function isLivewireUpdate(): bool
    {
        return app()->bound(HandleRequests::class)
            && app(HandleRequests::class)->isLivewireRoute();
    }
}
