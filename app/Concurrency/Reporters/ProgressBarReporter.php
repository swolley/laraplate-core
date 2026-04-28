<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency\Reporters;

use function Laravel\Prompts\progress;

use Illuminate\Support\Sleep;
use Laravel\Prompts\Progress;
use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;
use Modules\Core\Concurrency\Contracts\BatchReporter;
use Modules\Core\Concurrency\Stats\SlidingWindowStats;

/**
 * Renders run progress on the CLI using laravel/prompts.
 *
 * The hint shows live throughput and ETA computed on a sliding window so the
 * reading reflects current speed, not the warm-up phase.
 */
final class ProgressBarReporter implements BatchReporter
{
    private ?Progress $progress = null;

    private SlidingWindowStats $stats;

    private float $startTime = 0.0;

    private int $totalUnitsProcessed = 0;

    private int $completedTasks = 0;

    private int $totalTasks = 0;

    private int $totalUnits = 0;

    private string $initialHint;

    public function __construct(
        private readonly string $label,
        private readonly ?string $extraHint = null,
        int $statsWindowSeconds = 30,
    ) {
        $this->stats = new SlidingWindowStats($statsWindowSeconds);
        $this->initialHint = '';
    }

    public function start(int $totalTasks, int $totalUnits): void
    {
        $this->totalTasks = $totalTasks;
        $this->totalUnits = $totalUnits;
        $this->startTime = microtime(true);

        $this->progress = progress($this->label, max(1, $totalUnits));
        $this->initialHint = $this->extraHint ?? '';

        if ($this->initialHint !== '') {
            $this->progress->hint($this->initialHint);
        }

        $this->progress->start();
    }

    public function progress(BatchOutcome $outcome): void
    {
        if ($this->progress === null) {
            return;
        }

        $units = $outcome->unitsProcessed;

        if ($units > 0) {
            $this->progress->advance($units);
            $this->totalUnitsProcessed += $units;
        }

        $this->completedTasks++;
        $this->stats->record($outcome->duration, $units);
        $this->progress->hint($this->buildHint());
        $this->progress->render();
    }

    public function failure(BatchOutcome $outcome): void
    {
        if ($this->progress === null) {
            return;
        }

        $message = $outcome->error['message'] ?? 'unknown error';
        $this->progress->hint("<fg=red>Task {$outcome->taskId} failed: {$message}</>");
        $this->progress->render();
    }

    public function finish(BatchSummary $summary): void
    {
        if ($this->progress === null) {
            return;
        }

        if (! $summary->hasFailures()) {
            $this->progress->label(sprintf(
                'Successfully processed %d units across %d tasks in %.2fs',
                $summary->totalUnitsProcessed,
                $summary->totalTasks,
                $summary->totalDuration,
            ));
            $this->progress->render();

            // Brief pause so the final label is visible before the bar disappears.
            Sleep::usleep(200_000);
        }

        $this->progress->finish();
    }

    private function buildHint(): string
    {
        $parts = [];

        $parts[] = sprintf('Task %d/%d', $this->completedTasks, $this->totalTasks);

        $units_per_second = $this->stats->unitsPerSecond();

        if ($units_per_second <= 0 && $this->totalUnitsProcessed > 0) {
            $elapsed = microtime(true) - $this->startTime;

            if ($elapsed > 0) {
                $units_per_second = $this->totalUnitsProcessed / $elapsed;
            }
        }

        if ($units_per_second > 0) {
            $parts[] = $this->formatThroughput($units_per_second);

            if ($this->totalUnitsProcessed < $this->totalUnits) {
                $remaining = $this->totalUnits - $this->totalUnitsProcessed;
                $parts[] = $this->formatEta($remaining / $units_per_second);
            }
        }

        if ($this->initialHint !== '') {
            $parts[] = $this->initialHint;
        }

        return implode(' | ', $parts);
    }

    private function formatThroughput(float $units_per_second): string
    {
        if ($units_per_second < 0.001) {
            return sprintf('~%.2f units/h', $units_per_second * 3600);
        }

        if ($units_per_second < 1) {
            return sprintf('~%.2f units/min', $units_per_second * 60);
        }

        return sprintf('~%.1f units/s', $units_per_second);
    }

    private function formatEta(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('ETA: ~%.0f s', $seconds);
        }

        if ($seconds < 3600) {
            return sprintf('ETA: ~%.1f min', $seconds / 60);
        }

        return sprintf('ETA: ~%.1f h', $seconds / 3600);
    }
}
