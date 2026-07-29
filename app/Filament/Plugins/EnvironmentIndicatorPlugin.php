<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Filament\Services\ModuleVersionCatalog;
use Modules\Core\Models\User;

final class EnvironmentIndicatorPlugin implements Plugin
{
    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'core-environment-indicator';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            function (): string {
                if (! $this->isVisible()) {
                    return '';
                }

                $parts = [];

                if ($this->shouldShowDebugWarning()) {
                    $parts[] = view('core::filament.environment-indicator.debug-mode-warning')->render();
                }

                $parts[] = view('core::filament.environment-indicator.badge', [
                    'color' => $this->color(),
                    'environment' => ucfirst(app()->environment()),
                    'entries' => ModuleVersionCatalog::make()->entries(),
                ])->render();

                return '<div class="flex items-center gap-2">'.implode('', $parts).'</div>';
            },
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function isVisible(): bool
    {
        return $this->userCanSee(Filament::auth()->user());
    }

    public function userCanSee(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->isSuperAdmin();
    }

    public function shouldShowDebugWarning(?bool $is_production = null, ?bool $debug_enabled = null): bool
    {
        $is_production ??= app()->isProduction();
        $debug_enabled ??= app()->hasDebugModeEnabled();

        return $is_production && $debug_enabled;
    }

    /**
     * @return array<int, string>
     */
    public function color(): array
    {
        return match (app()->environment()) {
            'production' => Color::Red,
            'staging' => Color::Orange,
            'local', 'development' => Color::Blue,
            default => Color::Pink,
        };
    }
}
