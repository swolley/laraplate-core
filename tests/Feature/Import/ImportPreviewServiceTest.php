<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\EntityImporterRegistry;
use Modules\Core\Import\Support\ImportPreviewService;
use Modules\Core\Models\ImportSession;
use Modules\Core\Tests\Support\Import\StubWidgetImporter;

beforeEach(function (): void {
    Storage::fake('local');
    app(EntityImporterRegistry::class)->register(new StubWidgetImporter);
});

function previewSession(): ImportSession
{
    Storage::disk('local')->put('widgets.csv', "Code,Title\nA-1,Alpha\nB-2,Beta\nC-3,Gamma\n");

    return ImportSession::factory()->create([
        'entity_key' => 'stub.widget',
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'widgets.csv',
    ]);
}

test('detectColumns stores and returns the source headers', function (): void {
    $session = previewSession();

    $columns = app(ImportPreviewService::class)->detectColumns($session);

    expect($columns)->toBe(['Code', 'Title'])
        ->and($session->fresh()->detected_columns)->toBe(['Code', 'Title']);
});

test('preview returns columns, sample rows, fields and an auto-matched mapping', function (): void {
    $session = previewSession();

    $preview = app(ImportPreviewService::class)->preview($session, limit: 2);

    expect($preview->columns)->toBe(['Code', 'Title'])
        ->and($preview->rows)->toBe([
            ['Code' => 'A-1', 'Title' => 'Alpha'],
            ['Code' => 'B-2', 'Title' => 'Beta'],
        ])
        // 'code' matches the "Code" header by name; 'name' matches "Title" via its alias.
        ->and($preview->suggestedMapping)->toBe(['code' => 'Code', 'name' => 'Title'])
        ->and($preview->toArray()['fields'])->toBe([
            ['name' => 'code', 'label' => 'Code', 'required' => true],
            ['name' => 'name', 'label' => 'Name', 'required' => false],
        ]);
});
