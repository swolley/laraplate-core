<?php

declare(strict_types=1);

namespace Modules\Core\Services\Export;

use RuntimeException;

/**
 * Serializes explicit tabular recordsets to RFC 4180-style CSV strings.
 */
final class TabularCsvExporter
{
    /**
     * @param  list<array{key: string, label: string, format?: callable(mixed, mixed): string|null}>  $columns
     * @param  iterable<int, array<string, mixed>|object>  $rows
     */
    public function export(array $columns, iterable $rows): string
    {
        $csv_rows = [
            array_map(static fn (array $column): string => $column['label'], $columns),
        ];

        foreach ($rows as $row) {
            $csv_rows[] = array_map(
                fn (array $column): string => $this->formatValue($row, $column),
                $columns,
            );
        }

        return $this->writeRows($csv_rows);
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @param  array{key: string, label: string, format?: callable(mixed, mixed): string|null}  $column
     */
    private function formatValue(array|object $row, array $column): string
    {
        $value = $this->value($row, $column['key']);
        $formatter = $column['format'] ?? null;

        if (is_callable($formatter)) {
            return (string) $formatter($value, $row);
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    private function value(array|object $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeRows(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '', "\n");
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new RuntimeException('Unable to read temporary CSV stream.');
        }

        return $contents;
    }
}
