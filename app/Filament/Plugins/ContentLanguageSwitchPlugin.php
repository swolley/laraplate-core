<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Plugins;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Http\Middleware\ApplyBackofficeContentLocale;

/**
 * Lets the backoffice user pick the language translated content is read and written in, apart from the
 * language of the interface ({@see LanguageSwitchPlugin}). It is a working choice of this browser session,
 * applied by {@see ApplyBackofficeContentLocale}; without one the panel works in the default locale.
 */
final class ContentLanguageSwitchPlugin implements Plugin
{
    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'core-content-language-switch';
    }

    public function register(Panel $panel): void
    {
        $panel->userMenuItems([
            'content_language' => $this->action(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function action(): Action
    {
        $locales = static fn (): array => LanguageSwitchPlugin::make()->locales();

        return Action::make('content_language')
            ->label(static fn (): string => (string) __('app.menu.contentLanguage'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(static fn (): bool => count($locales()) > 1)
            ->modalWidth(Width::Small)
            ->fillForm(static fn (): array => ['locale' => LocaleContext::get()])
            ->schema([
                Select::make('locale')
                    ->label(static fn (): string => (string) __('app.menu.contentLanguage'))
                    ->options($locales)
                    ->in(static fn (): array => ApplyBackofficeContentLocale::availableLocales())
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(static function (array $data, Action $action): void {
                session()->put(ApplyBackofficeContentLocale::SESSION_KEY, $data['locale']);

                // A full reload: every table and form on the page was loaded in the previous content language.
                $action->redirect(Livewire::originalUrl(), navigate: false);
            });
    }
}
