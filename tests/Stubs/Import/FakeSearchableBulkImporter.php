<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Search\DeferredSearchIndexing;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Importer that saves searchable models through Eloquent, so the Scout
 * observer fires exactly as it does for a real import. With `interruptAfter`
 * it interrupts the run after that many records, as a Ctrl+C does.
 */
final readonly class FakeSearchableBulkImporter implements BulkImporterInterface
{
    public function __construct(
        public string $records = '0',
        public string $interruptAfter = '0',
    ) {}

    public function import(?OutputInterface $output = null): int
    {
        $records = max(0, (int) $this->records);

        for ($index = 0; $index < $records; $index++) {
            DeferredSearchableStubModel::query()->create(['name' => "record-{$index}"]);

            if ($index + 1 === (int) $this->interruptAfter) {
                app(DeferredSearchIndexing::class)->interrupt();
            }
        }

        return $records;
    }
}
