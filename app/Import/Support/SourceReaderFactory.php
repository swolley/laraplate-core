<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Modules\Core\Import\Contracts\SourceReaderInterface;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Readers\CsvSourceReader;
use Modules\Core\Import\Readers\JsonSourceReader;
use Modules\Core\Import\Readers\SpreadsheetSourceReader;

/**
 * Resolves the {@see SourceReaderInterface} for a source format. CSV → league/csv,
 * XLSX/ODS → openspout, JSON → in-process decode.
 */
final class SourceReaderFactory
{
    public function for(ImportSourceFormat $format): SourceReaderInterface
    {
        return match ($format) {
            ImportSourceFormat::Csv => new CsvSourceReader,
            ImportSourceFormat::Xlsx, ImportSourceFormat::Ods => new SpreadsheetSourceReader,
            ImportSourceFormat::Json => new JsonSourceReader,
        };
    }
}
