<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Jobs\ProcessImportSessionJob;
use Modules\Core\Models\ImportRowError;
use Modules\Core\Models\ImportSession;
use Modules\Core\Models\User;

beforeEach(function (): void {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

function uploadUsersCsv(): array
{
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.test\n");

    return test()->postJson('/app/crud/imports', ['file' => $file, 'entity_key' => 'core.user'])->json('data');
}

test('the entities endpoint lists registered importers with their fields', function (): void {
    $response = $this->getJson('/app/crud/imports/entities')->assertOk()->json('data');

    $keys = array_column($response, 'key');
    expect($keys)->toContain('core.user');
});

test('upload opens a draft session with detected columns and a suggested mapping', function (): void {
    $data = uploadUsersCsv();

    expect($data['status'])->toBe('draft')
        ->and($data['entity_key'])->toBe('core.user')
        ->and($data['detected_columns'])->toBe(['name', 'email']);

    $preview = $this->getJson("/app/crud/imports/{$data['id']}/preview")->assertOk()->json('data');
    expect($preview['columns'])->toBe(['name', 'email'])
        ->and($preview['suggested_mapping'])->toMatchArray(['name' => 'name', 'email' => 'email']);
});

test('running without every required field mapped is rejected', function (): void {
    $data = uploadUsersCsv();

    $this->putJson("/app/crud/imports/{$data['id']}/mapping", ['mapping' => ['name' => 'name']])->assertOk();

    $this->postJson("/app/crud/imports/{$data['id']}/run")
        ->assertStatus(422)
        ->assertJsonPath('errors.mapping', ['email']);
});

test('a fully mapped session queues the processing job', function (): void {
    Queue::fake();
    $data = uploadUsersCsv();

    $this->putJson("/app/crud/imports/{$data['id']}/mapping", ['mapping' => ['name' => 'name', 'email' => 'email']])->assertOk();

    $this->postJson("/app/crud/imports/{$data['id']}/run")
        ->assertOk()
        ->assertJsonPath('data.status', 'queued');

    Queue::assertPushed(ProcessImportSessionJob::class);
    expect(ImportSession::query()->find($data['id'])->status)->toBe(ImportSessionStatus::Queued);
});

test('the errors endpoint streams the failure report as csv', function (): void {
    $session = ImportSession::factory()->create(['status' => ImportSessionStatus::Completed]);
    ImportRowError::factory()->create([
        'import_session_id' => $session->id,
        'row_number' => 7,
        'errors' => ['email' => ['The email is invalid.']],
        'raw' => ['email' => 'nope'],
    ]);

    $response = $this->get("/app/crud/imports/{$session->id}/errors")->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('row_number')
        ->and($response->streamedContent())->toContain('The email is invalid.');
});
