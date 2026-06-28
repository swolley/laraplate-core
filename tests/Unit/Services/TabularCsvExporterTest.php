<?php

declare(strict_types=1);

use Modules\Core\Services\Export\TabularCsvExporter;

it('exports explicit tabular columns as csv', function (): void {
    $service = new TabularCsvExporter;
    $object = new stdClass;
    $object->code = '2000';
    $object->name = 'Object row';
    $object->amount = null;

    $csv = $service->export(
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
            ['code' => '1000', 'name' => 'Cash, main bank', 'amount' => '1250.5'],
            $object,
        ],
    );

    expect($csv)->toBe(implode("\n", [
        'Code,Name,Amount',
        '1000,"Cash, main bank",1250.5000',
        '2000,"Object row",0.0000',
        '',
    ]));
});
