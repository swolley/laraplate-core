<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Modules\Core\Models\SeedRun;

/**
 * Records the progress of a seeder orchestration run, one row per node, so a
 * failed run can be resumed instead of repeated from scratch.
 */
final class SeedLedger
{
    public function start(string $runId, string $node): void
    {
        SeedRun::query()->updateOrCreate(
            ['run_id' => $runId, 'node' => $node],
            ['status' => 'running', 'started_at' => now(), 'finished_at' => null, 'error' => null],
        );
    }

    public function succeed(string $runId, string $node, string $contentHash): void
    {
        SeedRun::query()
            ->where('run_id', $runId)
            ->where('node', $node)
            ->update([
                'status' => 'succeeded',
                'content_hash' => $contentHash,
                'finished_at' => now(),
            ]);
    }

    public function fail(string $runId, string $node, string $error): void
    {
        SeedRun::query()
            ->where('run_id', $runId)
            ->where('node', $node)
            ->update(['status' => 'failed', 'error' => $error, 'finished_at' => now()]);
    }

    /**
     * @return list<string>
     */
    public function completedNodes(string $runId): array
    {
        return SeedRun::query()
            ->where('run_id', $runId)
            ->where('status', 'succeeded')
            ->orderBy('id')
            ->pluck('node')
            ->all();
    }

    public function lastFailedRunId(): ?string
    {
        return SeedRun::query()
            ->where('status', 'failed')
            ->latest('id')
            ->value('run_id');
    }
}
