<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Modules\Core\Services\ApprovalNotificationService;

/**
 * Command to check for pending approvals and send notifications.
 * Designed to be run periodically via CronJob.
 */
final class CheckPendingApprovalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'approvals:check-pending
                            {--dry-run : Check without sending notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Check for pending approvals and notify admins <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(ApprovalNotificationService $service): int
    {
        if (! config('core.notifications.approvals.enabled', true)) {
            $this->info('Approval notifications are disabled.');

            return self::SUCCESS;
        }

        $this->info('Checking for pending approvals...');

        $pending = $service->getPendingApprovalsByEntity();

        if ($pending->isEmpty()) {
            $this->info('No pending approvals found beyond threshold.');

            return self::SUCCESS;
        }

        $this->table(
            ['Entity', 'Table', 'Pending Count', 'Oldest'],
            $pending->map(fn ($item): array => [
                $item['entity'],
                $item['table'],
                $item['count'],
                $item['oldest_at'] ?? 'N/A',
            ])->all(),
        );

        $total = $pending->sum('count');
        $this->newLine();
        $this->info("Total pending: {$total} record(s)");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - notifications not sent.');

            return self::SUCCESS;
        }

        $result = $service->checkAndNotify();

        if ($result['sent']) {
            $this->info('Notifications sent successfully.');
        } else {
            $this->warn('No notifications were sent (no recipients configured or feature disabled).');
        }

        return self::SUCCESS;
    }
}
