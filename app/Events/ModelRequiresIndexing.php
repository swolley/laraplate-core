<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Event emitted when a model requires indexing in the search engine.
 * This event allows multiple modules to register pre-processing jobs
 * (embeddings, translations, etc.) before the final indexing.
 */
class ModelRequiresIndexing
{
    /**
     * Minutes the event stays in the cache between the listeners that pre-process the model and the
     * listener that finalizes it.
     */
    public const int CACHE_TTL_MINUTES = 10;

    public bool $handled = false;

    /**
     * Array of pre-processing types that are required before indexing.
     * Each listener that dispatches a pre-processing job should add its type here.
     * Example: ['embeddings', 'translation', 'images'].
     */
    public array $required_pre_processing = [];

    /**
     * Array of pre-processing types that have been completed.
     * Populated by ModelPreProcessingCompleted events.
     */
    public array $completed_pre_processing = [];

    public function __construct(
        public readonly Model $model,
        public readonly bool $sync = false,
    ) {}

    /**
     * The cache key under which the event of a model waits for its pre-processing to complete: the
     * listeners of every module write and read the same key.
     */
    public static function cacheKey(Model $model): string
    {
        return "model_indexing:{$model->getTable()}:{$model->getKey()}";
    }

    public function markAsHandled(): void
    {
        $this->handled = true;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function addRequiredPreProcessing(string $type): void
    {
        if (! in_array($type, $this->required_pre_processing, true)) {
            $this->required_pre_processing[] = $type;
        }
    }

    public function markPreProcessingCompleted(string $type): void
    {
        if (! in_array($type, $this->completed_pre_processing, true)) {
            $this->completed_pre_processing[] = $type;
        }
    }

    public function allPreProcessingCompleted(): bool
    {
        if ($this->required_pre_processing === []) {
            return true; // No pre-processing required
        }

        sort($this->required_pre_processing);
        sort($this->completed_pre_processing);

        return $this->required_pre_processing === $this->completed_pre_processing;
    }
}
