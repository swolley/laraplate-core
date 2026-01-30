<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notification sent to admins when records are pending approval beyond threshold.
 */
final class PendingApprovalsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, array{entity: string, table: string, count: int, oldest_at: string|null}>  $pending_by_entity
     */
    public function __construct(
        private readonly Collection $pending_by_entity,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('core.notifications.approvals.channels', ['mail']);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->pending_by_entity->sum('count');
        $app_name = config('app.name');

        $message = (new MailMessage())
            ->subject("[{$app_name}] {$total} record(s) pending approval")
            ->greeting('Hello!')
            ->line("There are **{$total} records** waiting for moderation on {$app_name}.")
            ->line('');

        foreach ($this->pending_by_entity as $item) {
            $oldest_info = $item['oldest_at']
                ? " (oldest: {$item['oldest_at']})"
                : '';
            $message->line("- **{$item['entity']}**: {$item['count']} pending{$oldest_info}");
        }

        $message->line('')
            ->line('Please review these records at your earliest convenience.')
            ->salutation('Best regards');

        return $message;
    }

    /**
     * Get the Slack representation of the notification.
     * Requires laravel/slack-notification-channel package.
     *
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $total = $this->pending_by_entity->sum('count');
        $app_name = config('app.name');

        $details = $this->pending_by_entity
            ->map(fn ($item): string => "• *{$item['entity']}*: {$item['count']} pending")
            ->implode("\n");

        // Return array format for Slack webhook
        // If using laravel/slack-notification-channel, you can return SlackMessage instead
        return [
            'text' => "🔔 *{$total} records pending approval on {$app_name}*\n\n{$details}",
            'username' => $app_name,
        ];
    }

    /**
     * Get the array representation of the notification (for database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pending_approvals',
            'total_pending' => $this->pending_by_entity->sum('count'),
            'entities' => $this->pending_by_entity->pluck('count', 'entity')->all(),
            'details' => $this->pending_by_entity->all(),
        ];
    }
}
