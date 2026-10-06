<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Models\Modification;

/**
 * Emitted when an active modification may need automated review before humans finish.
 */
final class ModificationRequiresModeration
{
    /**
     * Minutes the event stays in the cache between the listeners that pre-process the modification and
     * the listener that finalizes it.
     */
    public const int CACHE_TTL_MINUTES = 10;

    public bool $handled = false;

    /**
     * @var list<string>
     */
    public array $required_pre_processing = [];

    /**
     * @var list<string>
     */
    public array $completed_pre_processing = [];

    public function __construct(
        public readonly Modification $modification,
        public readonly bool $sync = false,
    ) {}

    /**
     * The cache key under which the event of a modification waits for its pre-processing to complete.
     */
    public static function cacheKey(Modification $modification): string
    {
        return 'modification_moderation:' . $modification->getKey();
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
            return true;
        }

        $required = $this->required_pre_processing;
        $completed = $this->completed_pre_processing;
        sort($required);
        sort($completed);

        return $required === $completed;
    }
}
