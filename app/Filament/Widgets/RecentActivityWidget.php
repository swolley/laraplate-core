<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Exception;
use Filament\Widgets\Widget;
use Modules\Cms\Models\Content;

final class RecentActivityWidget extends Widget
{
    protected string $view = 'core::filament.widgets.recent-activity';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 50;

    protected function getViewData(): array
    {
        $data = [
            'recent_contents' => [],
            'recent_users' => [],
        ];

        // Recent contents (last 10)
        if (class_exists(Content::class)) {
            $data['recent_contents'] = Content::query()
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(function ($content) {
                    // Try to get title from translation or use a fallback
                    $title = 'Untitled';

                    try {
                        if (method_exists($content, 'getTranslation') && $content->getTranslation('title')) {
                            $title = $content->getTranslation('title');
                        } elseif (isset($content->title)) {
                            $title = $content->title;
                        } elseif (method_exists($content, 'translations')) {
                            $default_translation = $content->translations()->where('locale', app()->getLocale())->first();
                            $title = $default_translation?->title ?? 'Untitled';
                        }
                    } catch (Exception) {
                        // Fallback to Untitled
                    }

                    return [
                        'id' => $content->id,
                        'title' => $title,
                        'created_at' => $content->created_at?->diffForHumans(),
                        'url' => null, // Could be generated if there's a resource
                    ];
                })
                ->toArray();
        }

        // Recent users (last 5)
        $data['recent_users'] = User::query()
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->diffForHumans(),
            ])
            ->toArray();

        return $data;
    }
}
