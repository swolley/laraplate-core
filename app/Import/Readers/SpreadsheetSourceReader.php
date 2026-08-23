<?php

declare(strict_types=1);

namespace Modules\Core\Import\Readers;

use Modules\Core\Import\Contracts\SourceReaderInterface;
use Modules\Core\Import\Support\CellStringifier;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Override;

/**
 * Reads XLSX/ODS via openspout, streaming the first sheet: the first row is the
 * header, every later row is combined against it. openspout reads row-by-row, so a
 * large workbook never lands wholesale in memory.
 */
final class SpreadsheetSourceReader implements SourceReaderInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    #[Override]
    public function headers(string $path, array $options = []): array
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return $this->headerCells($row->toArray());
                }

                break;
            }

            return [];
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return iterable<int, array<string, string>>
     */
    #[Override]
    public function rows(string $path, array $options = []): iterable
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();

                    if ($headers === null) {
                        $headers = $this->headerCells($cells);

                        continue;
                    }

                    yield $this->combine($headers, $cells);
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function readerFor(string $path): ReaderInterface
    {
        return mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'ods'
            ? new OdsReader
            : new XlsxReader;
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function headerCells(array $cells): array
    {
        return array_values(array_map(
            static fn (mixed $cell): string => mb_trim(CellStringifier::stringify($cell)),
            $cells,
        ));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $cells
     * @return array<string, string>
     */
    private function combine(array $headers, array $cells): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = CellStringifier::stringify($cells[$index] ?? null);
        }

        return $row;
    }
}
