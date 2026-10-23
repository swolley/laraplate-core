<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Contracts\RefreshesConfigCache;
use Override;

/**
 * Counts the refreshes it is asked for and touches nothing: a test must never run the real `config:cache`.
 */
final class RecordingConfigCacheRefresher implements RefreshesConfigCache
{
    public int $calls = 0;

    #[Override]
    public function refresh(): void
    {
        $this->calls++;
    }
}
