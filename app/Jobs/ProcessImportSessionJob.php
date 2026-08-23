<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Events\ImportSessionFailed;
use Modules\Core\Import\Support\ImportRunner;
use Modules\Core\Models\ImportSession;
use Throwable;

/**
 * Runs one bulk import in the background via {@see ImportRunner}. Kept thin: the
 * runner owns streaming, chunked commits and the per-row failure report; the job
 * only marks the session failed and fires {@see ImportSessionFailed} if the run
 * aborts before finishing.
 */
final class ProcessImportSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $importSessionId) {}

    public function handle(ImportRunner $runner): void
    {
        $session = ImportSession::query()->find($this->importSessionId);

        if ($session === null || $session->status->isTerminal()) {
            return;
        }

        $runner->process($session);
    }

    public function failed(Throwable $exception): void
    {
        $session = ImportSession::query()->find($this->importSessionId);

        if ($session === null || $session->status->isTerminal()) {
            return;
        }

        $session->forceFill([
            'status' => ImportSessionStatus::Failed,
            'finished_at' => now(),
        ])->save();

        ImportSessionFailed::dispatch($session, mb_substr($exception->getMessage(), 0, 1000));
    }
}
