<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Support\Collection;

/**
 * Scout-shaped engine double that records the bulk `update` calls it receives.
 * Unlike {@see DegradingSearchEngineStub} it never fails, so the adaptive bulk
 * path can be exercised end to end and the batching asserted.
 */
final class RecordingSearchEngineStub
{
    public int $update_calls = 0;

    /**
     * Number of models handed to each update() call, in order.
     *
     * @var array<int, int>
     */
    public array $batch_sizes = [];

    public function indexExists(string $index): bool
    {
        return true;
    }

    public function createIndex(mixed $model): void {}

    /**
     * @param  Collection<int, mixed>  $models
     */
    public function update(mixed $models): void
    {
        $this->update_calls++;
        $this->batch_sizes[] = $models instanceof Collection ? $models->count() : (is_countable($models) ? count($models) : 1);
    }
}
