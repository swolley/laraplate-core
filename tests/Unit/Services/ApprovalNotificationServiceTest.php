<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;
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

    expect($pending)->toBeInstanceOf(\Illuminate\Support\Collection::class)
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
