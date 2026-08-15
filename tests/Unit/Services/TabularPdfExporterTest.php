<?php

declare(strict_types=1);

use Modules\Core\Services\Export\TabularPdfExporter;

it('renders explicit tabular columns as a pdf document', function (): void {
    $service = new TabularPdfExporter;
    $object = new stdClass;
    $object->code = '2000';
    $object->name = 'Object row';
    $object->amount = null;

    $pdf = $service->export(
        columns: [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Name'],
            [
                'key' => 'amount',
                'label' => 'Amount',
                'format' => static fn (mixed $value): string => $value === null ? '0.0000' : number_format((float) $value, 4, '.', ''),
            ],
        ],
        rows: [
            ['code' => '1000', 'name' => 'Cash', 'amount' => '1250.5'],
            $object,
        ],
        title: 'Settings export',
    );

    expect($pdf)->toStartWith('%PDF-1.4')
        ->and($pdf)->toContain('%%EOF')
        ->and($pdf)->toContain('Settings export')
        ->and($pdf)->toContain('Code | Name | Amount')
        ->and($pdf)->toContain('1000 | Cash | 1250.5000')
        ->and($pdf)->toContain('2000 | Object row | 0.0000');
});

it('keeps byte-accurate offsets with multibyte content', function (): void {
    $pdf = (new TabularPdfExporter)->export(
        columns: [['key' => 'name', 'label' => 'Name']],
        rows: [['name' => 'Città località àèìòù']],
        title: 'Località',
    );

    $offset = (int) preg_replace('/.*startxref\s+(\d+).*/s', '$1', $pdf);

    expect($pdf)->toStartWith('%PDF-1.4')
        ->and(mb_substr($pdf, $offset, 4, '8bit'))->toBe('xref');
});

it('caps the number of rendered rows and notes the overflow', function (): void {
    $rows = array_map(static fn (int $i): array => ['code' => (string) $i], range(1, 90));

    $pdf = (new TabularPdfExporter)->export(
        columns: [['key' => 'code', 'label' => 'Code']],
        rows: $rows,
    );

    expect($pdf)->toContain('... 10 more rows omitted');
});
