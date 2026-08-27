<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copies dotted database settings onto the config repository for this request.
 *
 * The overlay runs here rather than in a service provider's boot() because a
 * long-lived worker boots once: an overlay applied at boot would freeze
 * database-backed config for the whole process lifetime. Under PHP-FPM the cost is
 * unchanged, since boot already happened once per request.
 *
 * Console commands keep their boot-time overlay in CoreServiceProvider — they have no
 * middleware pipeline.
 */
final readonly class ApplyDatabaseSettingsOverlay
{
    public function __construct(
        private DatabaseConfigOverlay $overlay,
        private PerModelSettingResolver $settings,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->overlay->applyFromDatabase($this->settings);

        return $next($request);
    }
}
