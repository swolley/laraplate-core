<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Plugins;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Models\User;

/**
 * Lets the signed-in user pick the backoffice language from the user menu.
 *
 * The choice is stored on the user's own `lang`, which {@see \Modules\Core\Http\Middleware\LocalizationMiddleware}
 * applies on every request. Keeping a single source of truth means the SPA, other devices and
 * notifications follow the same language, unlike a session or cookie switch.
 */
final class LanguageSwitchPlugin implements Plugin
{
    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'core-language-switch';
    }

    public function register(Panel $panel): void
    {
        $panel->userMenuItems([
            'language' => $this->action(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Available locales, each labelled in its own language.
     *
     * @return array<string, string>
     */
    public function locales(): array
    {
        $configured = config('app.available_locales', translations());
        $options = [];

        foreach (is_array($configured) ? $configured : [] as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $options[$locale] = Lang::hasForLocale('app.language', $locale)
                ? (string) trans('app.language', [], $locale)
                : Str::upper($locale);
        }

        return $options;
    }

    public function action(): Action
    {
        return Action::make('language')
            ->label(static fn (): string => (string) __('app.menu.language'))
            ->icon(Heroicon::OutlinedLanguage)
            ->visible(fn (): bool => Filament::auth()->user() instanceof User && count($this->locales()) > 1)
            ->modalWidth(Width::Small)
            ->fillForm(static fn (): array => [
                'lang' => Filament::auth()->user()?->lang ?? app()->getLocale(),
            ])
            ->schema([
                Select::make('lang')
                    ->label(static fn (): string => (string) __('app.menu.language'))
                    ->options(fn (): array => $this->locales())
                    ->in(fn (): array => array_keys($this->locales()))
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(static function (array $data, Action $action): void {
                $user = Filament::auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $user->lang = $data['lang'];
                $user->save();

                // A full reload, not a SPA navigation: the layout and its scripts carry translated strings.
                $action->redirect(Livewire::originalUrl(), navigate: false);
            });
    }
}
