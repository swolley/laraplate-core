<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Notifications\PendingApprovalsNotification;
use Modules\Core\Services\ApprovalNotificationService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('returns early when approvals notifications are disabled', function (): void {
    Config::set('core.notifications.approvals.enabled', false);

    $service = new ApprovalNotificationService();

    $result = $service->checkAndNotify();

    expect($result)->toMatchArray([
        'sent' => false,
        'pending_count' => 0,
        'entities' => [],
    ]);
});

it('getPendingApprovalsByEntity returns empty collection when no models have approvals', function (): void {
    $service = new ApprovalNotificationService();

    $pending = $service->getPendingApprovalsByEntity();

    expect($pending)->toBeInstanceOf(Illuminate\Support\Collection::class)
        ->and($pending->isEmpty())->toBeTrue();
});

it('getModelsWithApprovals returns array and caches result', function (): void {
    $service = new ApprovalNotificationService();

    $models1 = $service->getModelsWithApprovals();
    $models2 = $service->getModelsWithApprovals();

    expect($models1)->toBeArray()->and($models2)->toBe($models1);
});

it('getThresholdForTable returns default when no setting exists', function (): void {
    $service = new ApprovalNotificationService();

    expect($service->getThresholdForTable('posts', 8))->toBe(8)
        ->and($service->getThresholdForTable('unknown_table', 24))->toBe(24);
});

it('checkAndNotify returns sent=false when no pending approvals are found', function (): void {
    $service = new ApprovalNotificationService();

    $result = $service->checkAndNotify();

    expect($result)->toMatchArray([
        'sent' => false,
        'pending_count' => 0,
        'entities' => [],
    ]);
});

it('getThresholdForTable returns the stored setting value', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'approval_threshold_posts',
        'value' => '48',
        'type' => SettingTypeEnum::STRING,
    ]);

    $service = new ApprovalNotificationService();

    expect($service->getThresholdForTable('posts', 8))->toBe(48);
});

it('usesHasApprovalsTrait detects the trait on models that use it', function (): void {
    $service = new ApprovalNotificationService();
    $method = (new ReflectionClass($service))->getMethod('usesHasApprovalsTrait');
    $method->setAccessible(true);

    expect($method->invoke($service, Setting::class))->toBeTrue()
        ->and($method->invoke($service, User::class))->toBeFalse();
});

it('getClassNameFromFile returns null when the path is outside the module app directory', function (): void {
    $service = new ApprovalNotificationService();
    $method = (new ReflectionClass($service))->getMethod('getClassNameFromFile');
    $method->setAccessible(true);

    expect($method->invoke($service, '/tmp/outside.php', '/var/www/Modules/Core'))->toBeNull();
});

it('sendNotifications dispatches PendingApprovalsNotification to configured role users', function (): void {
    Notification::fake();

    Config::set('core.notifications.approvals.recipients.roles', ['approval_admin']);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'approval_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['email' => 'approver_' . uniqid() . '@example.com']);
    $user->assignRole($role);

    $service = new ApprovalNotificationService();
    $pending = collect([
        [
            'entity' => 'Setting',
            'table' => 'settings',
            'count' => 2,
            'oldest_at' => now()->subDay()->toIso8601String(),
        ],
    ]);

    $send = (new ReflectionClass($service))->getMethod('sendNotifications');
    $send->setAccessible(true);
    $send->invoke($service, $pending);

    Notification::assertSentTo($user, PendingApprovalsNotification::class);
});

it('sendNotifications sends nothing when there are no recipients', function (): void {
    Notification::fake();

    Role::factory()->create(['name' => 'role_without_assigned_users', 'guard_name' => 'web']);
    Config::set('core.notifications.approvals.recipients.roles', ['role_without_assigned_users']);

    $service = new ApprovalNotificationService();
    $pending = collect([
        [
            'entity' => 'Setting',
            'table' => 'settings',
            'count' => 1,
            'oldest_at' => null,
        ],
    ]);

    $send = (new ReflectionClass($service))->getMethod('sendNotifications');
    $send->setAccessible(true);
    $send->invoke($service, $pending);

    Notification::assertNothingSent();
});
