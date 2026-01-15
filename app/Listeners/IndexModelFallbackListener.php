<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Search\Jobs\IndexInSearchJob;

final class IndexModelFallbackListener
{
    public function handle(ModelRequiresIndexing $event): void
    {
        // If AI has already handled, do nothing
        if ($event->isHandled()) {
            return;
        }

        // AI didn't handle (disabled or module not configured)
        // Dispatch only indexing without embeddings
        if ($event->sync) {
            (new IndexInSearchJob($event->model))->handle();
        } else {
            dispatch(new IndexInSearchJob($event->model));
        }
    }
}
