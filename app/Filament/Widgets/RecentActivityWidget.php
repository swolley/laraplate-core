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

    private int $limit = 8;

    protected function getViewData(): array
    {
        $data = [
            'recent_contents' => [],
            'recent_users' => [],
        ];

        // Recent contents (last 10)
        if (class_exists(Content::class)) {
            $data['recent_contents'] = Content::query()
                ->with('translations', fn ($query) => $query->select(['title', 'locale', 'content_id']))
                ->latest('updated_at')
                ->limit($this->limit)
                ->get(['id', 'presettable_id', 'updated_at'])
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
                        'locale' => $content->locale,
                        'updated_at' => $content->updated_at?->diffForHumans(),
                    ];
                })
                ->toArray();
        }

        // Recent users (last 5)
        $data['recent_users'] = User::query()
            ->latest('last_login_at')
            ->whereNotNull('last_login_at')
            ->whereDoesntHave('roles', function ($query): void {
                $query->where('name', config('permission.roles.superadmin'));
            })
            ->limit($this->limit)
            ->get(['id', 'name', 'email', 'last_login_at'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_login_at' => $user->last_login_at?->diffForHumans(),
            ])
            ->toArray();

        return $data;
    }
}
