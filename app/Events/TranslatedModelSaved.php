<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Event emitted when a model requires automatic translation.
 * This event is separate from ModelRequiresIndexing but can be
 * synchronized with indexing if the model is searchable.
 */
class TranslatedModelSaved
{
    public bool $handled = false;

    public function __construct(
        public readonly Model $model,
        public readonly array $locales = [],
        public readonly bool $force = false,
    ) {}

    public function markAsHandled(): void
    {
        $this->handled = true;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }
}
