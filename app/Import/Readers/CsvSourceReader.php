<?php

declare(strict_types=1);

namespace Modules\Core\Import\Readers;

use League\Csv\Reader;
use Modules\Core\Import\Contracts\SourceReaderInterface;
use Modules\Core\Import\Support\CellStringifier;
use Override;

/**
 * Reads CSV/TSV via league/csv, streaming records keyed by the header row. The
 * delimiter is a caller option (default comma), so the same reader handles
 * tab- and semicolon-separated files.
 */
final class CsvSourceReader implements SourceReaderInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    #[Override]
    public function headers(string $path, array $options = []): array
    {
        return array_values(array_map(
            static fn (string $header): string => mb_trim($header),
            $this->reader($path, $options)->getHeader(),
        ));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return iterable<int, array<string, string>>
     */
    #[Override]
    public function rows(string $path, array $options = []): iterable
    {
        foreach ($this->reader($path, $options)->getRecords() as $record) {
            $row = [];

            foreach ($record as $header => $value) {
                $row[mb_trim((string) $header)] = CellStringifier::stringify($value);
            }

            yield $row;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function reader(string $path, array $options): Reader
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $delimiter = $options['delimiter'] ?? null;

        if (is_string($delimiter) && mb_strlen($delimiter) === 1) {
            $reader->setDelimiter($delimiter);
        }

        return $reader;
    }
}
