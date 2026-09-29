<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\DeferredSearchIndexing;

/**
 * Indexes one chunk flushed by {@see DeferredSearchIndexing} on the search
 * queue. It carries keys only: the models are reloaded when the job runs, so
 * the chunk is indexed as it is then, and rows deleted meanwhile are skipped.
 */
final class IndexDeferredSearchChunkJob extends CommonSearchJob
{
    /**
     * @param  class-string<Model&ISearchableModel>  $modelClass
     * @param  list<int|string>  $keys
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly array $keys,
    ) {
        parent::__construct();
    }

    public function handle(DeferredSearchIndexing $indexing): void
    {
        $indexing->indexChunk($this->modelClass, $this->keys);
    }
}
