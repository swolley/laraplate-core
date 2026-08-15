<?php

declare(strict_types=1);

namespace Modules\Core\Services\Export;

/**
 * Renders explicit tabular recordsets to a minimal, self-contained PDF string.
 *
 * Mirrors {@see TabularCsvExporter}'s column contract so the same recordset can
 * be exported to either format. The output is a single-page, monospaced dump
 * suitable for lightweight reports and previews, not a paginated layout engine.
 *
 * The low-level PDF plumbing ({@see pdfFromLines}) is `protected` so module
 * exporters with a custom row layout can extend this class instead of
 * duplicating the byte-offset bookkeeping.
 */
class TabularPdfExporter
{
    protected const int MAX_LINE_LENGTH = 110;

    private const int MAX_ROWS = 80;

    /**
     * @param  list<array{key: string, label: string, format?: callable(mixed, mixed): string|null}>  $columns
     * @param  iterable<int, array<string, mixed>|object>  $rows
     */
    public function export(array $columns, iterable $rows, string $title = 'Export'): string
    {
        $lines = [$title, 'Generated at ' . now()->toISOString(), ''];
        $lines[] = $this->rowLine(array_map(static fn (array $column): string => $column['label'], $columns));

        $count = 0;
        $overflow = 0;

        foreach ($rows as $row) {
            if ($count >= self::MAX_ROWS) {
                $overflow++;

                continue;
            }

            $lines[] = $this->rowLine(array_map(
                fn (array $column): string => $this->formatValue($row, $column),
                $columns,
            ));
            $count++;
        }

        if ($overflow > 0) {
            $lines[] = sprintf('... %d more rows omitted', $overflow);
        }

        return $this->pdfFromLines($lines);
    }

    /**
     * The PDF xref table and stream `/Length` are byte offsets, so lengths are
     * measured with the `8bit` encoding to count bytes rather than code points.
     *
     * @param  list<string>  $lines
     */
    protected function pdfFromLines(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n50 790 Td\n";

        foreach ($lines as $index => $line) {
            $prefix = $index === 0 ? '' : "0 -14 Td\n";
            $content .= $prefix . '(' . $this->escapePdfText(mb_substr($line, 0, self::MAX_LINE_LENGTH)) . ") Tj\n";
        }

        $content .= "ET\n";

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length ' . mb_strlen($content, '8bit') . " >> stream\n" . $content . 'endstream endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = mb_strlen($pdf, '8bit');
            $pdf .= $object . "\n";
        }

        $xref_offset = mb_strlen($pdf, '8bit');
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= mb_str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF\n";

        return $pdf;
    }

    protected function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * @param  list<string>  $cells
     */
    private function rowLine(array $cells): string
    {
        return implode(' | ', $cells);
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @param  array{key: string, label: string, format?: callable(mixed, mixed): string|null}  $column
     */
    private function formatValue(array|object $row, array $column): string
    {
        $value = is_array($row) ? ($row[$column['key']] ?? null) : ($row->{$column['key']} ?? null);
        $formatter = $column['format'] ?? null;

        if (is_callable($formatter)) {
            return (string) $formatter($value, $row);
        }

        if ($value === null || is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
