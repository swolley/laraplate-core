<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Events\ImportSessionFailed;
use Modules\Core\Import\Support\EntityImporterRegistry;
use Modules\Core\Jobs\ProcessImportSessionJob;
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

test('the job runs an import to completion', function (): void {
    Storage::disk('local')->put('widgets.csv', "code,name\na,Alpha\nb,Beta\n");
    $session = ImportSession::factory()->create([
        'entity_key' => 'stub.widget',
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'widgets.csv',
        'mapping' => ['code' => 'code', 'name' => 'name'],
    ]);

    (new ProcessImportSessionJob($session->id))->handle(app(Modules\Core\Import\Support\ImportRunner::class));

    expect($session->fresh()->status)->toBe(ImportSessionStatus::Completed)
        ->and(StubWidget::query()->count())->toBe(2);
});

test('the failed hook marks the session failed and fires the event', function (): void {
    Event::fake([ImportSessionFailed::class]);
    $session = ImportSession::factory()->create(['status' => ImportSessionStatus::Processing]);

    (new ProcessImportSessionJob($session->id))->failed(new RuntimeException('reader exploded'));

    $session->refresh();
    expect($session->status)->toBe(ImportSessionStatus::Failed)
        ->and($session->finished_at)->not->toBeNull();

    Event::assertDispatched(
        ImportSessionFailed::class,
        fn (ImportSessionFailed $event): bool => $event->session->is($session) && $event->reason === 'reader exploded',
    );
});
