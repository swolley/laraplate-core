<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency\Reporters;

use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;
use Modules\Core\Concurrency\Contracts\BatchReporter;

/**
 * Reporter that swallows every event. Default for the runner.
 */
final readonly class NullReporter implements BatchReporter
{
    public function start(int $totalTasks, int $totalUnits): void {}

    public function progress(BatchOutcome $outcome): void {}

    public function failure(BatchOutcome $outcome): void {}

    public function finish(BatchSummary $summary): void {}
}
