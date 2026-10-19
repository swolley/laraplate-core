<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Override;
use Symfony\Component\Console\Command\Command as CommandExit;

/**
 * Not available until compaction is redefined (decision D18 of the revision-centric history review): a cut-off
 * revision, an anchor and a merged diff. The previous implementation appended a snapshot of the live record to
 * every history, adding a revision with no change, and deleted nothing although it said so, so the command
 * refuses rather than write that revision.
 */
final class CompactVersions extends Command
{
    #[Override]
    protected $signature = 'versions:compact {modelClass? : Fully-qualified model class (must use HasVersions)} {id? : Primary key of a single record}';

    #[Override]
    protected $description = 'Compact the version history (not available yet: redefined by decision D18). <fg=green>(⚡ Modules\Core)</fg=green>';

    public function handle(): int
    {
        $this->error('Version compaction is not available yet: it is being redefined (cut-off revision, anchor, merged diff), and the previous command added a revision with no change to every history. No version was written.');

        return CommandExit::FAILURE;
    }
}
