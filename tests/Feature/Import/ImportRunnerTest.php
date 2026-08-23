<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Events\ImportSessionCompleted;
use Modules\Core\Import\Support\EntityImporterRegistry;
use Modules\Core\Import\Support\ImportRunner;
use Modules\Core\Models\ImportSession;
use Modules\Core\Tests\Support\Import\StubWidget;
use Modules\Core\Tests\Support\Import\StubWidgetImporter;

beforeEach(function (): void {
    Storage::fake('local');
    Schema::create('stub_widgets', static function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('name');
        $table->timestamps();
    });
    app(EntityImporterRegistry::class)->register(new StubWidgetImporter);
});

afterEach(function (): void {
    Schema::dropIfExists('stub_widgets');
});

/**
 * @param  array<string, string>  $mapping
 */
function widgetSession(string $csv, array $mapping): ImportSession
{
    Storage::disk('local')->put('widgets.csv', $csv);

    return ImportSession::factory()->create([
        'entity_key' => 'stub.widget',
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'widgets.csv',
        'mapping' => $mapping,
    ]);
}

test('the runner counts outcomes, records row failures, and rolls back a failing write', function (): void {
    Event::fake([ImportSessionCompleted::class]);

    $session = widgetSession(
        "code,name\na,Alpha\n,Beta\nboom1,Crash\na,Alpha2\n",
        ['code' => 'code', 'name' => 'name'],
    );

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->status)->toBe(ImportSessionStatus::Completed)
        ->and($session->processed_rows)->toBe(4)
        ->and($session->created_rows)->toBe(1)
        ->and($session->updated_rows)->toBe(1)
        ->and($session->failed_rows)->toBe(2)
        ->and($session->skipped_rows)->toBe(0)
        ->and($session->finished_at)->not->toBeNull();

    // Only the good code survives; the blank-code and the write-then-throw rows do not.
    expect(StubWidget::query()->pluck('name', 'code')->all())->toBe(['a' => 'Alpha2'])
        ->and(StubWidget::query()->where('code', 'boom1')->exists())->toBeFalse();

    $errors = $session->rowErrors()->orderBy('row_number')->get();
    expect($errors)->toHaveCount(2)
        ->and($errors[0]->row_number)->toBe(2)
        ->and($errors[0]->errors)->toHaveKey('code')
        ->and($errors[1]->row_number)->toBe(3)
        ->and($errors[1]->errors)->toHaveKey('_')
        ->and($errors[1]->raw)->toBe(['code' => 'boom1', 'name' => 'Crash']);

    Event::assertDispatched(ImportSessionCompleted::class, fn (ImportSessionCompleted $event): bool => $event->session->is($session));
});

test('an unmapped column is dropped before the importer sees the row', function (): void {
    $session = widgetSession("code,name\na,Alpha\n", ['code' => 'code']); // name not mapped

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->created_rows)->toBe(1)
        ->and(StubWidget::query()->where('code', 'a')->value('name'))->toBe('');
});
