<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Event emitted by each pre-processing job when it completes.
 * The finalize listener will check if all pre-processing are completed
 * before dispatching the indexing job.
 */
class ModelPreProcessingCompleted
{
    public function __construct(
        public readonly Model $model,
        public readonly string $processing_type, // 'embeddings', 'translation', 'images', etc.
    ) {}
}
