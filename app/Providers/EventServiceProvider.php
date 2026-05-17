<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModificationPreProcessingCompleted;
use Modules\Core\Events\ModificationRequiresModeration;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\DynamicContentsService;
use Override;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    #[Override]
    protected $listen = [
        \Modules\Core\Events\ModelRequiresIndexing::class => [
            \Modules\Core\Listeners\IndexModelFallbackListener::class, // Executes after AI listeners
        ],
        \Modules\Core\Events\ModelPreProcessingCompleted::class => [
            \Modules\Core\Listeners\FinalizeModelIndexingListener::class, // Handles finalization
        ],
        ModificationRequiresModeration::class => [
            \Modules\Core\Listeners\ModificationModerationFallbackListener::class,
        ],
        ModificationPreProcessingCompleted::class => [
            \Modules\Core\Listeners\FinalizeModificationModerationListener::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    #[Override]
    protected static $shouldDiscoverEvents = true;

    #[Override]
    public function boot(): void
    {
        Event::listen('eloquent.saved: ' . Modification::class, function (Modification $modification): void {
            if (! $modification->active || ! $modification->wasRecentlyCreated) {
                return;
            }

            event(new ModificationRequiresModeration($modification));
        });

        Event::listen([
            'eloquent.saved: ' . CronJob::class,
            'eloquent.deleted: ' . CronJob::class,
        ], function (Model $model): void {
            if ($model instanceof CronJob) {
                Cache::forget($model->getTable());
            } elseif ($model instanceof Fieldable || $model instanceof Preset) {
                $this->clearPresetCache();
            } elseif ($model instanceof Entity) {
                $this->clearEntityCache($model);
            }
        });
    }

    /**
     * Configure the proper event listeners for email verification.
     */
    #[Override]
    protected function configureEmailVerification(): void
    {
        //
    }

    private function clearEntityCache(Entity $model): void
    {
        Cache::forget($model->getCacheKey());
        DynamicContentsService::getInstance()->clearEntitiesCache();
        $this->clearPresetCache();
    }

    private function clearPresetCache(): void
    {
        Cache::forget('presets');
        Cache::memo()->forget('presets');
        DynamicContentsService::getInstance()->clearPresetsCache();
        DynamicContentsService::getInstance()->clearPresettablesCache();
    }
}
