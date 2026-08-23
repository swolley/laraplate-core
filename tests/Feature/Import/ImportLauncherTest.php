<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Support\ImportLauncher;
use Modules\Core\Jobs\ProcessImportSessionJob;
use Modules\Core\Models\ImportSession;

test('missingRequiredFields reports required fields with no mapped column', function (): void {
    $session = ImportSession::factory()->create([
        'entity_key' => 'core.user',
        'mapping' => ['name' => 'Full name'], // email (required) unmapped
    ]);

    expect(app(ImportLauncher::class)->missingRequiredFields($session))->toBe(['email']);
});

test('a fully mapped session has no missing required fields', function (): void {
    $session = ImportSession::factory()->create([
        'entity_key' => 'core.user',
        'mapping' => ['name' => 'Full name', 'email' => 'E-mail'],
    ]);

    expect(app(ImportLauncher::class)->missingRequiredFields($session))->toBe([]);
});

test('queue marks the session queued and dispatches the job; terminal ones are not launchable', function (): void {
    Queue::fake();
    $launcher = app(ImportLauncher::class);

    $draft = ImportSession::factory()->create(['status' => ImportSessionStatus::Draft]);
    expect($launcher->isLaunchable($draft))->toBeTrue();

    $launcher->queue($draft);
    Queue::assertPushed(ProcessImportSessionJob::class);
    expect($draft->fresh()->status)->toBe(ImportSessionStatus::Queued);

    $done = ImportSession::factory()->create(['status' => ImportSessionStatus::Completed]);
    expect($launcher->isLaunchable($done))->toBeFalse();
});
