<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

/**
 * Reads a tabular source file into headers and a streamed sequence of associative
 * rows. Implementations exist per {@see \Modules\Core\Import\Enums\ImportSourceFormat}
 * and must stream — never load a whole large file into memory — so an import can
 * scale past what fits in RAM.
 */
interface SourceReaderInterface
{
    /**
     * The source column headers, in file order.
     *
     * @param  array<string, mixed>  $options  Format-specific hints (e.g. `delimiter`, `has_header`).
     * @return list<string>
     */
    public function headers(string $path, array $options = []): array;

    /**
     * The data rows, each an associative array keyed by header. Streamed lazily.
     *
     * @param  array<string, mixed>  $options
     * @return iterable<int, array<string, string>>
     */
    public function rows(string $path, array $options = []): iterable;
}
