<?php

declare(strict_types=1);

namespace Modules\Core\Services\Export {
    function fopen(string $filename, string $mode, bool $use_include_path = false, mixed $context = null): mixed
    {
        if (($GLOBALS['tabular_csv_exporter_fopen_failure'] ?? false) === true) {
            return false;
        }

        if ($context === null) {
            return \fopen($filename, $mode, $use_include_path);
        }

        return \fopen($filename, $mode, $use_include_path, $context);
    }

    function stream_get_contents(mixed $stream, ?int $length = null, int $offset = -1): string|false
    {
        if (($GLOBALS['tabular_csv_exporter_stream_failure'] ?? false) === true) {
            return false;
        }

        if ($length === null) {
            return \stream_get_contents($stream);
        }

        return \stream_get_contents($stream, $length, $offset);
    }
}

namespace {
use Modules\Core\Services\Export\TabularCsvExporter;

beforeEach(function (): void {
    $GLOBALS['tabular_csv_exporter_fopen_failure'] = false;
    $GLOBALS['tabular_csv_exporter_stream_failure'] = false;
});

afterEach(function (): void {
    $GLOBALS['tabular_csv_exporter_fopen_failure'] = false;
    $GLOBALS['tabular_csv_exporter_stream_failure'] = false;
});

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

it('fails when the temporary csv stream cannot be opened', function (): void {
    $GLOBALS['tabular_csv_exporter_fopen_failure'] = true;

    expect(fn () => (new TabularCsvExporter)->export(
        columns: [['key' => 'code', 'label' => 'Code']],
        rows: [['code' => '1000']],
    ))->toThrow(RuntimeException::class, 'Unable to open temporary CSV stream.');
});

it('fails when the temporary csv stream cannot be read', function (): void {
    $GLOBALS['tabular_csv_exporter_stream_failure'] = true;

    expect(fn () => (new TabularCsvExporter)->export(
        columns: [['key' => 'code', 'label' => 'Code']],
        rows: [['code' => '1000']],
    ))->toThrow(RuntimeException::class, 'Unable to read temporary CSV stream.');
});
}
