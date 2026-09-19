<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Batch counterpart of {@see ModelRequiresIndexing}: emitted once for a whole
 * chunk of models on the bulk indexing path, so a listener can pre-process them
 * together (e.g. embed every text in one batched call) instead of once per
 * model. Unlike the per-model event this carries no pre-processing coordination:
 * it is dispatched synchronously and the bulk indexer writes to the engine only
 * after every listener has returned.
 */
final class ModelsRequireIndexing
{
    /**
     * @param  Collection<int, Model>  $models
     */
    public function __construct(
        public readonly Collection $models,
        public readonly bool $sync = false,
    ) {}
}
