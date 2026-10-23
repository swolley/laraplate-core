<?php

declare(strict_types=1);

namespace Modules\Core\Support\Env;

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\RefreshesConfigCache;
use Override;

/**
 * Runs `config:clear` and then `config:cache`, so a changed `.env` takes effect on a deployment that caches its configuration.
 */
final readonly class ConfigCacheRefresher implements RefreshesConfigCache
{
    #[Override]
    public function refresh(): void
    {
        Artisan::call('config:clear');
        Artisan::call('config:cache');
    }
}
