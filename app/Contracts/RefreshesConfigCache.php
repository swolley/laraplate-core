<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Rebuilds the application's configuration cache. A contract so that a test never runs the real
 * `config:cache`, which would make the whole suite read the cached (production) configuration.
 */
interface RefreshesConfigCache
{
    public function refresh(): void;
}
