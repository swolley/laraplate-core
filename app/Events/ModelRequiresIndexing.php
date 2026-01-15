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
    public bool $handled = false;

    /**
     * Array of pre-processing types that are required before indexing.
     * Each listener that dispatches a pre-processing job should add its type here.
     * Example: ['embeddings', 'translation', 'images']
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
        if (empty($this->required_pre_processing)) {
            return true; // No pre-processing required
        }

        sort($this->required_pre_processing);
        sort($this->completed_pre_processing);

        return $this->required_pre_processing === $this->completed_pre_processing;
    }
}
