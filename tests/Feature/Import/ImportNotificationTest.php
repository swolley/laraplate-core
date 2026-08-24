<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Import\Events\ImportSessionCompleted;
use Modules\Core\Import\Events\ImportSessionFailed;
use Modules\Core\Models\ImportSession;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function importSessionForUser(User $user, array $attributes = []): ImportSession
{
    return ImportSession::factory()->create(array_merge([
        'user_id' => $user->id,
        'entity_key' => 'cms.content',
        'created_rows' => 5,
        'updated_rows' => 2,
        'skipped_rows' => 0,
        'failed_rows' => 1,
    ], $attributes));
}

test('a completed import notifies the owner with a module-scoped payload', function (): void {
    $user = User::factory()->create();
    $session = importSessionForUser($user);

    event(new ImportSessionCompleted($session));

    $notification = $user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->module_name)->toBe('cms')
        ->and($notification->data['type'])->toBe('import.finished')
        ->and($notification->data['level'])->toBe('warning') // failed_rows > 0
        ->and($notification->data['scope'])->toBe('cms')
        ->and($notification->data['action'])->toMatchArray(['target' => 'import_session', 'id' => $session->id, 'view' => 'errors'])
        ->and($notification->data['meta']['failed'])->toBe(1);
});

test('a clean completed import is a success-level notification', function (): void {
    $user = User::factory()->create();
    event(new ImportSessionCompleted(importSessionForUser($user, ['failed_rows' => 0])));

    expect($user->fresh()->notifications()->first()->data['level'])->toBe('success');
});

test('a failed import notifies with a danger level and the reason', function (): void {
    $user = User::factory()->create();
    event(new ImportSessionFailed(importSessionForUser($user, ['failed_rows' => 0]), 'Boom'));

    $notification = $user->fresh()->notifications()->first();

    expect($notification->data['level'])->toBe('danger')
        ->and($notification->data['body'])->toBe('Boom');
});

test('a session with no owner is not notified into the void', function (): void {
    $session = ImportSession::factory()->create(['user_id' => null, 'entity_key' => 'core.user']);

    event(new ImportSessionCompleted($session));

    expect(DB::table('notifications')->count())->toBe(0);
});

test('the tray endpoints list, filter by scope, and mark read', function (): void {
    $user = User::factory()->create();
    event(new ImportSessionCompleted(importSessionForUser($user, ['entity_key' => 'cms.content'])));
    event(new ImportSessionCompleted(importSessionForUser($user, ['entity_key' => 'erp.item'])));

    $this->actingAs($user);

    $this->getJson('/app/notifications')
        ->assertOk()
        ->assertJsonPath('meta.unread', 2)
        ->assertJsonCount(2, 'data');

    $this->getJson('/app/notifications?scope=erp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.scope', 'erp');

    $this->getJson('/app/notifications/unread-count')->assertJsonPath('data.unread', 2);

    $id = $user->notifications()->first()->id;
    $this->postJson("/app/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
    expect($user->fresh()->unreadNotifications()->count())->toBe(1);

    $this->postJson('/app/notifications/read-all')->assertJsonPath('data.unread', 0);
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another user notification read', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    event(new ImportSessionCompleted(importSessionForUser($owner)));
    $id = $owner->notifications()->first()->id;

    $this->actingAs($intruder)->postJson("/app/notifications/{$id}/read")->assertNotFound();
});
