<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Console\CheckPendingApprovalsCommand;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\ApprovalNotificationService;
use Modules\Core\Tests\Fixtures\HandleTestContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function checkPendingApprovalsCommandWithOutput(CheckPendingApprovalsCommand $command): CheckPendingApprovalsCommand
{
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $output_reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $output_reflection->setValue($command, $output);
    $command->setLaravel(app());

    return $command;
}

it('covers CheckPendingApprovalsCommand branches', function (): void {
    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config = [];
    }

    $service = new ApprovalNotificationService();
    $service_reflection = new ReflectionProperty(ApprovalNotificationService::class, 'models_cache');
    $service_reflection->setAccessible(true);
    $service_reflection->setValue($service, ['users' => User::class]);

    $this->app->instance(ApprovalNotificationService::class, $service);

    config(['core.notifications.approvals.enabled' => false]);

    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config['core.notifications.approvals.enabled'] = false;
    }

    $disabled = checkPendingApprovalsCommandWithOutput(new CheckPendingApprovalsCommand());
    expect($disabled->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);

    config(['core.notifications.approvals.enabled' => true]);

    if (class_exists(HandleTestContext::class)) {
        HandleTestContext::$config['core.notifications.approvals.enabled'] = true;
    }

    Modification::query()->delete();
    $empty = checkPendingApprovalsCommandWithOutput(new CheckPendingApprovalsCommand());
    expect($empty->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);

    Modification::query()->insert([
        [
            'modifiable_id' => 1,
            'modifiable_type' => User::class,
            'modifier_id' => 1,
            'modifier_type' => User::class,
            'active' => true,
            'is_update' => true,
            'approvers_required' => 1,
            'disapprovers_required' => 1,
            'md5' => md5('a'),
            'modifications' => '{}',
            'created_at' => now()->subHours(24),
            'updated_at' => now()->subHours(24),
        ],
    ]);
    Setting::query()->withoutGlobalScopes()->updateOrCreate(
        ['name' => 'approval_threshold_users'],
        ['group_name' => 'core', 'value' => 1],
    );
    expect($service->getPendingApprovalsByEntity()->isEmpty())->toBeFalse();

    $dry_run = checkPendingApprovalsCommandWithOutput(new CheckPendingApprovalsCommand());
    expect($dry_run->run(new ArrayInput(['--dry-run' => true]), new BufferedOutput()))->toBe(0);

    Notification::fake();
    $admin_role = Role::factory()->create(['name' => 'admin', 'guard_name' => 'web']);
    Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['email' => 'admin-notify@example.test']);
    $admin->assignRole($admin_role);
    $notify = checkPendingApprovalsCommandWithOutput(new CheckPendingApprovalsCommand());
    expect($notify->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);

    User::query()->whereKey($admin->id)->delete();
    $no_recipients = checkPendingApprovalsCommandWithOutput(new CheckPendingApprovalsCommand());
    expect($no_recipients->run(new ArrayInput([]), new BufferedOutput()))->toBe(0);
});
