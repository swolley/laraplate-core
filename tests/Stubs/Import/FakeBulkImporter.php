<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Illuminate\Support\Facades\DB;
use Modules\Core\Import\Contracts\BulkImporterInterface;

final class FakeBulkImporter implements BulkImporterInterface
{
    public const string TABLE = 'core_fake_import_rows';

    /** @var array<string, mixed> */
    public static array $arguments = [];

    public function __construct(
        public readonly string $records = '0',
        public readonly bool $dryRun = false,
        public readonly ?int $limit = null,
    ) {}

    public function import(): int
    {
        self::$arguments = [
            'records' => $this->records,
            'dryRun' => $this->dryRun,
            'limit' => $this->limit,
        ];

        $records = max(0, (int) $this->records);

        if ($this->limit !== null && $this->limit > 0) {
            $records = min($records, $this->limit);
        }

        for ($index = 0; $index < $records; $index++) {
            DB::table(self::TABLE)->insert(['name' => "record-{$index}"]);
        }

        return $records;
    }
}
