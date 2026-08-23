<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\ImportRunner;
use Modules\Core\Models\ImportSession;
use Modules\Core\Models\RecordOrigin;
use Modules\Core\Models\User;

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * @param  array<string, string>  $mapping
 */
function userImportSession(string $csv, array $mapping = ['name' => 'name', 'email' => 'email']): ImportSession
{
    Storage::disk('local')->put('users.csv', $csv);

    return ImportSession::factory()->create([
        'entity_key' => 'core.user',
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'users.csv',
        'original_filename' => 'users.csv',
        'mapping' => $mapping,
    ]);
}

test('the user importer creates users and stamps provenance, idempotently by email', function (): void {
    $session = userImportSession("name,email\nAda Lovelace,ada@example.test\nAlan Turing,alan@example.test\n");

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->created_rows)->toBe(2)
        ->and($session->failed_rows)->toBe(0)
        ->and(User::query()->whereIn('email', ['ada@example.test', 'alan@example.test'])->count())->toBe(2)
        ->and(RecordOrigin::query()->where('source_key', 'import:core.user')->count())->toBe(2);

    // Re-import the same file with a changed name: matched by email, so updated not duplicated.
    $again = userImportSession("name,email\nAda Byron,ada@example.test\n");
    app(ImportRunner::class)->process($again);

    expect($again->fresh()->updated_rows)->toBe(1)
        ->and(User::query()->where('email', 'ada@example.test')->value('name'))->toBe('Ada Byron')
        ->and(User::query()->where('email', 'ada@example.test')->count())->toBe(1);
});

test('a row with an invalid email is recorded as a failure and skipped', function (): void {
    $session = userImportSession("name,email\nNoEmail,not-an-email\n");

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->created_rows)->toBe(0)
        ->and($session->failed_rows)->toBe(1)
        ->and($session->rowErrors()->first()->errors)->toHaveKey('email')
        ->and(User::query()->where('name', 'NoEmail')->exists())->toBeFalse();
});
