<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;
use Modules\Core\Notifications\PendingApprovalsNotification;
use ReflectionClass;
use Throwable;

/**
 * Service for checking and notifying about pending approval records.
 * Auto-discovers models using HasApprovals trait and sends notifications
 * to configured recipients when records have been pending beyond threshold.
 */
final class ApprovalNotificationService
{
    /**
     * @var array<string, class-string<Model>>|null
     */
    private ?array $models_cache = null;

    /**
     * Check for pending approvals and send notifications if needed.
     *
     * @return array{sent: bool, pending_count: int, entities: array<string, int>}
     */
    public function checkAndNotify(): array
    {
        if (! config('core.notifications.approvals.enabled', true)) {
            return ['sent' => false, 'pending_count' => 0, 'entities' => []];
        }

        $pending_by_entity = $this->getPendingApprovalsByEntity();

        if ($pending_by_entity->isEmpty()) {
            return ['sent' => false, 'pending_count' => 0, 'entities' => []];
        }

        $total_pending = $pending_by_entity->sum('count');
        $entities = $pending_by_entity->pluck('count', 'entity')->all();

        $this->sendNotifications($pending_by_entity);

        return [
            'sent' => true,
            'pending_count' => $total_pending,
            'entities' => $entities,
        ];
    }

    /**
     * Get pending approvals grouped by entity, filtered by threshold.
     *
     * @return Collection<int, array{entity: string, table: string, count: int, oldest_at: string}>
     */
    public function getPendingApprovalsByEntity(): Collection
    {
        $models = $this->getModelsWithApprovals();
        $default_threshold = config('core.notifications.approvals.default_threshold_hours', 8);
        $result = collect();

        foreach ($models as $table => $model_class) {
            $threshold_hours = $this->getThresholdForTable($table, $default_threshold);
            $threshold_date = now()->subHours($threshold_hours);

            $pending_count = Modification::query()
                ->where('modifiable_type', $model_class)
                ->where('active', true)
                ->where('created_at', '<=', $threshold_date)
                ->count();

            if ($pending_count > 0) {
                $oldest = Modification::query()
                    ->where('modifiable_type', $model_class)
                    ->where('active', true)
                    ->where('created_at', '<=', $threshold_date)
                    ->oldest()
                    ->first();

                $result->push([
                    'entity' => class_basename($model_class),
                    'table' => $table,
                    'count' => $pending_count,
                    'oldest_at' => $oldest?->created_at?->toIso8601String(),
                ]);
            }
        }

        return $result->sortByDesc('count')->values();
    }

    /**
     * Get all models that use the HasApprovals trait.
     *
     * @return array<string, class-string<Model>>
     */
    public function getModelsWithApprovals(): array
    {
        if ($this->models_cache !== null) {
            return $this->models_cache;
        }

        $this->models_cache = [];
        $modules_path = base_path('Modules');

        if (! File::isDirectory($modules_path)) {
            return $this->models_cache;
        }

        $modules = File::directories($modules_path);

        foreach ($modules as $module_path) {
            $models_path = $module_path . '/app/Models';

            if (! File::isDirectory($models_path)) {
                continue;
            }

            $files = File::files($models_path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class_name = $this->getClassNameFromFile($file->getPathname(), $module_path);

                if ($class_name === null || ! class_exists($class_name)) {
                    continue;
                }

                if ($this->usesHasApprovalsTrait($class_name)) {
                    /** @var class-string<Model> $class_name */
                    $instance = new $class_name();
                    $this->models_cache[$instance->getTable()] = $class_name;
                }
            }
        }

        return $this->models_cache;
    }

    /**
     * Get threshold hours for a specific table from settings.
     */
    public function getThresholdForTable(string $table, int $default): int
    {
        $setting_name = "approval_threshold_{$table}";

        $setting = Setting::query()
            ->withoutGlobalScopes()
            ->where('name', $setting_name)
            ->first();

        if ($setting === null) {
            return $default;
        }

        return (int) $setting->value;
    }

    /**
     * Send notifications to configured recipients.
     *
     * @param  Collection<int, array{entity: string, table: string, count: int, oldest_at: string|null}>  $pending_by_entity
     */
    private function sendNotifications(Collection $pending_by_entity): void
    {
        $recipients = $this->getNotificationRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new PendingApprovalsNotification($pending_by_entity);

        Notification::send($recipients, $notification);
    }

    /**
     * Get users who should receive approval notifications.
     *
     * @return Collection<int, \Modules\Core\Models\User>
     */
    private function getNotificationRecipients(): Collection
    {
        $roles = config('core.notifications.approvals.recipients.roles', ['admin', 'superadmin']);
        $user_class = user_class();

        return $user_class::query()
            ->role($roles)
            ->whereNotNull('email')
            ->get();
    }

    /**
     * Extract class name from file path.
     */
    private function getClassNameFromFile(string $file_path, string $module_path): ?string
    {
        $module_name = basename($module_path);
        $relative_path = str_replace($module_path . '/app/', '', $file_path);
        $relative_path = str_replace('.php', '', $relative_path);
        $relative_path = str_replace('/', '\\', $relative_path);

        return "Modules\\{$module_name}\\{$relative_path}";
    }

    /**
     * Check if a class uses the HasApprovals trait.
     */
    private function usesHasApprovalsTrait(string $class_name): bool
    {
        try {
            $reflection = new ReflectionClass($class_name);

            // Check direct traits
            $traits = $reflection->getTraitNames();

            if (in_array(HasApprovals::class, $traits, true)) {
                return true;
            }

            // Check parent classes
            $parent = $reflection->getParentClass();

            while ($parent !== false) {
                $parent_traits = $parent->getTraitNames();

                if (in_array(HasApprovals::class, $parent_traits, true)) {
                    return true;
                }

                $parent = $parent->getParentClass();
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
