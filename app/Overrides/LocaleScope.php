<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Core\Helpers\LocaleContext;

final class LocaleScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $default_locale = config('app.locale');
        $current_locale = LocaleContext::get();
        $fallback_enabled = LocaleContext::isFallbackEnabled();

        // Se la lingua corrente è quella di default, filtra solo per quella
        if ($current_locale === $default_locale) {
            $builder->whereHas('translations', function (Builder $query) use ($default_locale): void {
                $query->where('locale', $default_locale);
            });
        } elseif ($fallback_enabled) {
            // Se fallback è abilitato: mostra contenuti con traduzione corrente O di default
            $builder->whereHas('translations', function (Builder $query) use ($current_locale, $default_locale): void {
                $query->where('locale', $current_locale)
                    ->orWhere('locale', $default_locale);
            });
        } else {
            // Se fallback è disabilitato: mostra SOLO contenuti con traduzione nella lingua corrente
            $builder->whereHas('translations', function (Builder $query) use ($current_locale): void {
                $query->where('locale', $current_locale);
            });
        }

        // Eager load traduzione corrente (con fallback se abilitato)
        $this->eagerLoadTranslation($builder, $current_locale, $default_locale, $fallback_enabled);
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('forLocale', function (Builder $builder, ?string $locale = null, ?bool $with_fallback = null): Builder {
            $locale ??= LocaleContext::get();
            $default_locale = config('app.locale');
            $fallback_enabled = $with_fallback ?? LocaleContext::isFallbackEnabled();

            // Rimuovi il global scope
            $builder->withoutGlobalScope($this);

            // Applica filtro per lingua specifica
            if ($fallback_enabled && $locale !== $default_locale) {
                // Mostra contenuti con traduzione richiesta O di default
                $builder->whereHas('translations', function (Builder $query) use ($locale, $default_locale): void {
                    $query->where('locale', $locale)
                        ->orWhere('locale', $default_locale);
                });
            } else {
                // Mostra SOLO contenuti con traduzione richiesta
                $builder->whereHas('translations', function (Builder $query) use ($locale): void {
                    $query->where('locale', $locale);
                });
            }

            // Carica traduzione
            $builder->with(['translation' => function ($query) use ($locale, $default_locale, $fallback_enabled): void {
                if ($fallback_enabled) {
                    $query->where(function ($q) use ($locale, $default_locale): void {
                        $q->where('locale', $locale)
                            ->orWhere('locale', $default_locale);
                    })
                        ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale]);
                } else {
                    $query->where('locale', $locale);
                }
            }]);

            return $builder;
        });
    }

    /**
     * Eager load translation with fallback logic.
     *
     * @param  Builder<Model>  $builder
     */
    private function eagerLoadTranslation(
        Builder $builder,
        string $current_locale,
        string $default_locale,
        bool $fallback_enabled,
    ): void {
        $builder->with(['translation' => function ($query) use ($current_locale, $default_locale, $fallback_enabled): void {
            if ($fallback_enabled) {
                // Carica traduzione corrente, se non esiste usa quella di default
                $query->where(function ($q) use ($current_locale, $default_locale): void {
                    $q->where('locale', $current_locale)
                        ->orWhere('locale', $default_locale);
                })
                    ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$current_locale]);
            } else {
                // Carica SOLO traduzione corrente (null se non esiste)
                $query->where('locale', $current_locale);
            }
        }]);
    }
}
