<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Modules\Core\Notifications\PendingApprovalsNotification;


it('via returns configured channels', function (): void {
    config(['core.notifications.approvals.channels' => ['mail', 'database']]);
    $notification = new PendingApprovalsNotification(new Collection());

    expect($notification->via(new stdClass()))->toBe(['mail', 'database']);
});

it('toMail builds message with entity lines and optional oldest date', function (): void {
    config(['app.name' => 'Laraplate']);
    $notification = new PendingApprovalsNotification(collect([
        ['entity' => 'Setting', 'table' => 'settings', 'count' => 2, 'oldest_at' => '2026-01-01T10:00:00+00:00'],
        ['entity' => 'User', 'table' => 'users', 'count' => 1, 'oldest_at' => null],
    ]));

    $mail = $notification->toMail(new stdClass());
    $array = $mail->toArray();

    expect($array['subject'])->toContain('3 record(s) pending approval')
        ->and(implode("\n", $array['introLines']))->toContain('Setting')
        ->and(implode("\n", $array['introLines']))->toContain('oldest: 2026-01-01T10:00:00+00:00')
        ->and(implode("\n", $array['introLines']))->toContain('User');
});

it('toSlack returns summary payload with bullet details', function (): void {
    config(['app.name' => 'Laraplate']);
    $notification = new PendingApprovalsNotification(collect([
        ['entity' => 'Setting', 'table' => 'settings', 'count' => 2, 'oldest_at' => null],
    ]));

    $payload = $notification->toSlack(new stdClass());

    expect($payload)->toHaveKeys(['text', 'username'])
        ->and($payload['username'])->toBe('Laraplate')
        ->and($payload['text'])->toContain('2 records pending approval')
        ->and($payload['text'])->toContain('Setting');
});

it('toArray returns compact database payload', function (): void {
    $details = collect([
        ['entity' => 'Setting', 'table' => 'settings', 'count' => 2, 'oldest_at' => null],
        ['entity' => 'User', 'table' => 'users', 'count' => 1, 'oldest_at' => null],
    ]);
    $notification = new PendingApprovalsNotification($details);

    $array = $notification->toArray(new stdClass());

    expect($array['type'])->toBe('pending_approvals')
        ->and($array['total_pending'])->toBe(3)
        ->and($array['entities'])->toBe(['Setting' => 2, 'User' => 1])
        ->and($array['details'])->toBe($details->all());
});
