<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use const SIGINT;

use Closure;
use Modules\Core\Search\DeferredSearchIndexing;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handles SIGINT (Ctrl+C) and SIGTERM during a bulk import whose search
 * indexing is deferred. When recorded models are still waiting to be indexed,
 * Ctrl+C asks the operator whether to index them before quitting, quit at
 * once, or resume. SIGTERM, or a run that cannot ask, indexes them and quits.
 *
 * Indexing before quitting unwinds the run through
 * {@see DeferredSearchIndexing::interrupt()} instead of flushing inside this
 * handler, so a second signal still reaches it and quits at once.
 */
final class ImportInterruptHandler
{
    public const string FINISH = 'finish';

    public const string QUIT = 'quit';

    public const string RESUME = 'resume';

    private bool $interrupting = false;

    private int $exitCode = 130;

    /**
     * @param  (Closure(bool $flushing, int $pending): string)|null  $ask  asks the operator on Ctrl+C and returns one of the choice constants; null when the run cannot ask
     * @param  Closure(int $code): void  $exit  terminates the process with the given status
     */
    public function __construct(
        private readonly DeferredSearchIndexing $deferredIndexing,
        private readonly OutputInterface $output,
        private readonly ?Closure $ask,
        private readonly Closure $exit,
    ) {}

    public function __invoke(int $signal): void
    {
        $this->exitCode = 128 + $signal;

        if ($this->interrupting || ! $this->deferredIndexing->hasPendingWork()) {
            ($this->exit)($this->exitCode);

            return;
        }

        $choice = $signal === SIGINT && $this->ask instanceof Closure
            ? ($this->ask)($this->deferredIndexing->isFlushing(), $this->deferredIndexing->pendingCount())
            : self::FINISH;

        if ($choice === self::RESUME) {
            return;
        }

        if ($choice === self::QUIT) {
            $this->output->writeln('<comment>Quitting without indexing the records imported since the last flush: run scout:import to index them.</comment>');
            ($this->exit)($this->exitCode);

            return;
        }

        $this->interrupting = true;
        $this->output->writeln('<comment>Indexing the records imported so far, then quitting. Press Ctrl+C again to quit at once.</comment>');
        $this->deferredIndexing->interrupt();
    }

    /**
     * Exit status for a run stopped by this handler: 128 plus the signal number.
     */
    public function exitCode(): int
    {
        return $this->exitCode;
    }
}
