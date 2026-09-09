<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Throwable;

/**
 * Scout-shaped engine double whose index and document calls always fail with a
 * caller supplied exception.
 */
final class DegradingSearchEngineStub
{
    public int $index_checks = 0;

    public int $update_calls = 0;

    public function __construct(private readonly Throwable $failure) {}

    public function indexExists(string $index): bool
    {
        $this->index_checks++;

        throw $this->failure;
    }

    public function createIndex(mixed $model): void
    {
        throw $this->failure;
    }

    public function update(mixed $models): void
    {
        $this->update_calls++;

        throw $this->failure;
    }
}
