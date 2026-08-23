<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\SourceReaderFactory;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    Storage::fake('local');
});

test('the csv reader streams rows keyed by a trimmed header', function (): void {
    Storage::disk('local')->put('widgets.csv', "code, name\nA-1,Alpha\nB-2,Beta\n");
    $reader = app(SourceReaderFactory::class)->for(ImportSourceFormat::Csv);
    $path = Storage::disk('local')->path('widgets.csv');

    expect($reader->headers($path))->toBe(['code', 'name'])
        ->and(iterator_to_array($reader->rows($path)))->toBe([
            ['code' => 'A-1', 'name' => 'Alpha'],
            ['code' => 'B-2', 'name' => 'Beta'],
        ]);
});

test('the csv reader honours a custom delimiter', function (): void {
    Storage::disk('local')->put('widgets.csv', "code;name\nA-1;Alpha\n");
    $reader = app(SourceReaderFactory::class)->for(ImportSourceFormat::Csv);
    $path = Storage::disk('local')->path('widgets.csv');

    expect($reader->headers($path, ['delimiter' => ';']))->toBe(['code', 'name'])
        ->and(iterator_to_array($reader->rows($path, ['delimiter' => ';'])))->toBe([
            ['code' => 'A-1', 'name' => 'Alpha'],
        ]);
});

test('the json reader unions object keys as headers', function (): void {
    Storage::disk('local')->put('widgets.json', json_encode([
        ['code' => 'A-1', 'name' => 'Alpha'],
        ['code' => 'B-2', 'extra' => 'x'],
    ]));
    $reader = app(SourceReaderFactory::class)->for(ImportSourceFormat::Json);
    $path = Storage::disk('local')->path('widgets.json');

    expect($reader->headers($path))->toBe(['code', 'name', 'extra'])
        ->and(iterator_to_array($reader->rows($path)))->toBe([
            ['code' => 'A-1', 'name' => 'Alpha'],
            ['code' => 'B-2', 'extra' => 'x'],
        ]);
});

test('the json reader unwraps a single wrapping key', function (): void {
    Storage::disk('local')->put('widgets.json', json_encode(['data' => [['code' => 'A-1']]]));
    $reader = app(SourceReaderFactory::class)->for(ImportSourceFormat::Json);
    $path = Storage::disk('local')->path('widgets.json');

    expect(iterator_to_array($reader->rows($path)))->toBe([['code' => 'A-1']]);
});

test('the spreadsheet reader streams the first sheet of an xlsx', function (): void {
    $path = Storage::disk('local')->path('widgets.xlsx');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['code', 'name']));
    $writer->addRow(Row::fromValues(['A-1', 'Alpha']));
    $writer->addRow(Row::fromValues(['B-2', 'Beta']));
    $writer->close();

    $reader = app(SourceReaderFactory::class)->for(ImportSourceFormat::Xlsx);

    expect($reader->headers($path))->toBe(['code', 'name'])
        ->and(iterator_to_array($reader->rows($path)))->toBe([
            ['code' => 'A-1', 'name' => 'Alpha'],
            ['code' => 'B-2', 'name' => 'Beta'],
        ]);
});
