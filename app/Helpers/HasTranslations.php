<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Modules\Cms\Jobs\TranslateModelJob;
use Modules\Core\Overrides\LocaleScope;
use Override;

/**
 * Trait for models that store translatable fields in a separate translation table.
 *
 * Architecture:
 * - Translatable fields are determined dynamically from the translation model's $fillable
 * - Values are stored in pending_translations until model is saved
 * - On save, pending_translations are persisted to the translation table
 * - Custom accessors (getXxxAttribute) and mutators (setXxxAttribute) are supported
 *
 * Usage:
 * - Add trait to model: use HasTranslations;
 * - Create translation model with $fillable containing translatable fields
 * - Translation model class is auto-resolved: ModelTranslation in Translations subfolder
 *
 * @template TModel of Model
 */
trait HasTranslations
{
    /**
     * Temporary storage for translatable fields to be saved.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $pending_translations = [];

    /**
     * Current locale context for setter operations.
     */
    protected ?string $current_setter_locale = null;

    /**
     * Cached translatable fields to avoid creating new instances.
     * Key: model class name, Value: array of translatable fields.
     *
     * @var array<class-string, array<string>>
     */
    protected static array $cached_translatable_fields = [];

    /**
     * Override getAttribute to handle translatable fields.
     *
     * Priority:
     * 1. If translatable field with custom accessor → call accessor with translated value
     * 2. If translatable field → return translated value
     * 3. Otherwise → delegate to parent
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        if ($this->isTranslatableField($key)) {
            $value = $this->getTranslatableFieldValue($key);

            // Check for custom accessor (e.g., getNameAttribute)
            $accessor = 'get' . Str::studly($key) . 'Attribute';

            if (method_exists($this, $accessor)) {
                return $this->{$accessor}($value);
            }

            return $value;
        }

        return parent::getAttribute($key);
    }

    /**
     * Override setAttribute to handle translatable fields.
     *
     * Priority:
     * 1. If translatable field with custom mutator → call mutator (it should call setTranslatableFieldValue)
     * 2. If translatable field → store in pending translations
     * 3. Otherwise → delegate to parent
     *
     * @param  string  $key
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableField($key)) {
            // Check for custom mutator (e.g., setNameAttribute)
            $mutator = 'set' . Str::studly($key) . 'Attribute';

            if (method_exists($this, $mutator)) {
                $this->{$mutator}($value);

                return $this;
            }

            $this->setTranslatableFieldValue($key, $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get translatable fields for this model.
     * Cached to avoid creating new instances and potential recursion.
     */
    public function getTranslatableFields(): array
    {
        $model_class = static::class;

        // Cache per classe per evitare ricorsione durante l'inizializzazione
        if (! isset(static::$cached_translatable_fields[$model_class])) {
            static::$cached_translatable_fields[$model_class] = array_filter(
                (new (static::getTranslationModelClass()))->getFillable(),
                static fn (string $field): bool => $field !== 'locale' && ! str_ends_with($field, '_id'),
            );
        }

        return static::$cached_translatable_fields[$model_class];
    }

    public function isTranslatableField(string $field): bool
    {
        return in_array($field, $this->getTranslatableFields(), true);
    }

    /**
     * Get the translations relation.
     *
     * @return HasMany<Model>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(static::getTranslationModelClass());
    }

    /**
     * Get the translation for current locale (with conditional fallback).
     *
     * @return HasOne<Model>
     */
    public function translation(): HasOne
    {
        $current_locale = LocaleContext::get();
        $default_locale = config('app.locale');
        $fallback_enabled = LocaleContext::isFallbackEnabled();

        $relation = $this->hasOne(static::getTranslationModelClass());

        if ($fallback_enabled) {
            // Se fallback abilitato: traduzione corrente o di default
            $relation->where(function ($query) use ($current_locale, $default_locale): void {
                $query->where('locale', $current_locale)
                    ->orWhere('locale', $default_locale);
            })
                ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$current_locale]);
        } else {
            // Se fallback disabilitato: SOLO traduzione corrente (null se non esiste)
            $relation->where('locale', $current_locale);
        }

        return $relation;
    }

    /**
     * Set locale context for next assignments.
     */
    public function inLocale(string $locale): self
    {
        $this->current_setter_locale = $locale;

        return $this;
    }

    /**
     * Get translation for specific locale.
     */
    public function getTranslation(?string $locale = null, ?bool $with_fallback = null): ?Model
    {
        $locale ??= LocaleContext::get();
        $default_locale = config('app.locale');
        $fallback_enabled = $with_fallback ?? LocaleContext::isFallbackEnabled();

        // Prova a ottenere traduzione per la lingua richiesta
        $translation = $this->translations()->where('locale', $locale)->first();

        // Se non esiste e fallback è abilitato, usa quella di default
        if (! $translation && $fallback_enabled && $locale !== $default_locale) {
            return $this->translations()->where('locale', $default_locale)->first();
        }

        return $translation;
    }

    /**
     * Set translation for specific locale.
     */
    public function setTranslation(string $locale, array $data): self
    {
        $translation = $this->translations()->where('locale', $locale)->first();

        if ($translation) {
            $translation->update($data);
        } else {
            $this->translations()->create(array_merge($data, ['locale' => $locale]));
        }

        // Reload if current translation
        if ($locale === LocaleContext::get()) {
            $this->load('translation');
        }

        return $this;
    }

    /**
     * Update translation for specific locale.
     */
    public function updateTranslation(string $locale, array $data): self
    {
        return $this->setTranslation($locale, $data);
    }

    /**
     * Check if translation exists for locale.
     */
    public function hasTranslation(?string $locale = null): bool
    {
        $locale ??= LocaleContext::get();

        return $this->translations()->where('locale', $locale)->exists();
    }

    /**
     * Get all translations.
     */
    public function getAllTranslations(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->translations;
    }

    public function toArray(?array $parsed = null): array
    {
        $content = $parsed ?? (method_exists(parent::class, 'toArray') ? parent::toArray() : $this->attributesToArray());
        $translation = $this->getRelationValue('translation');

        if ($translation) {
            foreach ($this->getTranslatableFields() as $field) {
                if (isset($translation->{$field}) && ! in_array($field, $this->hidden, true)) {
                    $content[$field] = $translation->{$field};
                }
            }
        }

        $locale = $this->getCurrentLocale();

        if (isset($this->pending_translations[$locale])) {
            return array_merge($content, $this->pending_translations[$locale]);
        }

        return $content;
    }

    public function initializeHasTranslations(): void
    {
        if (! in_array('translation', $this->hidden, true)) {
            $this->hidden[] = 'translation';
        }

        if (! in_array('translation', $this->with, true)) {
            $this->with[] = 'translation';
        }

        if (! in_array('locale', $this->appends, true)) {
            $this->appends[] = 'locale';
        }
    }

    /**
     * Get the translation model class name.
     *
     * @return class-string<Model>
     */
    protected static function getTranslationModelClass(): string
    {
        // Get the base class (Content, Category, Tag) even if called from child class (Article, etc.)
        $current_class = static::class;

        // If this is a child class (e.g., Article extends Content), use the parent class
        $parent_class = get_parent_class($current_class);

        if ($parent_class && $parent_class !== Model::class) {
            $current_class = $parent_class;
        }

        return str_replace('\\Models\\', '\\Models\\Translations\\', $current_class) . 'Translation';
    }

    /**
     * Boot the translations trait.
     */
    protected static function bootHasTranslations(): void
    {
        static::addGlobalScope(new LocaleScope());

        // Handle saved event to save translations (after model has ID)
        static::saved(function (Model $model): void {
            /** @var Model&HasTranslations $model */
            $model->savePendingTranslations();
        });

        static::created(function (Model $model): void {
            /** @phpstan-ignore-next-line */
            if (! config('core.auto_translate_enabled', false)) {
                return;
            }

            // Check if default translation exists
            $default_locale = config('app.locale');

            if (! $model->hasTranslation($default_locale)) {
                return;
            }

            // Dispatch translation job
            dispatch(new TranslateModelJob($model));
        });

        static::updated(function (Model $model): void {
            /** @phpstan-ignore-next-line */
            if (! config('core.auto_translate_enabled', false)) {
                return;
            }

            // Only translate if default translation was modified
            $default_locale = config('app.locale');
            $default_translation = $model->getTranslation($default_locale);

            if (! $default_translation || ! $default_translation->wasChanged()) {
                return;
            }

            // Dispatch translation job to update translations
            dispatch(new TranslateModelJob($model, [], true));
        });
    }

    /**
     * Eloquent accessor for locale attribute.
     * This makes locale available in toArray() and JSON serialization.
     */
    protected function locale(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getCurrentLocale(),
        );
    }

    /**
     * Save pending translations.
     */
    protected function savePendingTranslations(): void
    {
        if (empty($this->pending_translations)) {
            return;
        }

        foreach ($this->pending_translations as $locale => $fields) {
            $translation = $this->translations()->where('locale', $locale)->first();

            if ($translation) {
                $translation->update($fields);
            } else {
                $this->translations()->create(array_merge($fields, ['locale' => $locale]));
            }
        }

        // Clear pending translations
        $this->pending_translations = [];
        $this->current_setter_locale = null;

        // Reload translation relation
        $this->load('translation');
    }

    /**
     * Scope to filter by specific locale (removes default locale scope).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forLocale(Builder $query, ?string $locale = null, ?bool $with_fallback = null): Builder
    {
        $locale ??= LocaleContext::get();
        $default_locale = config('app.locale');
        $fallback_enabled = $with_fallback ?? LocaleContext::isFallbackEnabled();

        // Rimuovi il global scope di default
        $query->withoutGlobalScope(LocaleScope::class);

        // Filtra per lingua specifica
        if ($fallback_enabled && $locale !== $default_locale) {
            // Mostra contenuti con traduzione richiesta O di default
            $query->whereHas('translations', function (Builder $q) use ($locale, $default_locale): void {
                $q->where('locale', $locale)
                    ->orWhere('locale', $default_locale);
            });
        } else {
            // Mostra SOLO contenuti con traduzione richiesta
            $query->whereHas('translations', function (Builder $q) use ($locale): void {
                $q->where('locale', $locale);
            });
        }

        // Carica traduzione
        $query->with(['translation' => function ($q) use ($locale, $default_locale, $fallback_enabled): void {
            if ($fallback_enabled) {
                $q->where(function ($sub_q) use ($locale, $default_locale): void {
                    $sub_q->where('locale', $locale)
                        ->orWhere('locale', $default_locale);
                })
                    ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale]);
            } else {
                $q->where('locale', $locale);
            }
        }]);

        return $query;
    }

    /**
     * Scope to include translation without filtering.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withTranslation(Builder $query, ?string $locale = null, ?bool $with_fallback = null): Builder
    {
        $locale ??= LocaleContext::get();
        $default_locale = config('app.locale');
        $fallback_enabled = $with_fallback ?? LocaleContext::isFallbackEnabled();

        $query->with(['translation' => function ($q) use ($locale, $default_locale, $fallback_enabled): void {
            if ($fallback_enabled) {
                $q->where(function ($sub_q) use ($locale, $default_locale): void {
                    $sub_q->where('locale', $locale)
                        ->orWhere('locale', $default_locale);
                })
                    ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale]);
            } else {
                $q->where('locale', $locale);
            }
        }]);

        return $query;
    }

    /**
     * Get default translation.
     */
    protected function getDefaultTranslation(): ?Model
    {
        return $this->translations()
            ->where('locale', config('app.locale'))
            ->first();
    }

    /**
     * Get value for a translatable field.
     *
     * Checks in order:
     * 1. Pending translations for current locale
     * 2. Pending translations for default locale (if fallback enabled)
     * 3. Saved translation relation
     * 4. Default translation (if fallback enabled)
     *
     * @param  string  $key  The field name
     * @return mixed The field value or null
     */
    protected function getTranslatableFieldValue(string $key): mixed
    {
        $current_locale = LocaleContext::get();
        $default_locale = config('app.locale');
        $fallback_enabled = LocaleContext::isFallbackEnabled();

        // First, check pending translations (values set but not yet saved)
        if (isset($this->pending_translations[$current_locale][$key])) {
            return $this->pending_translations[$current_locale][$key];
        }

        // If fallback enabled, check pending translation for default locale
        if ($fallback_enabled && isset($this->pending_translations[$default_locale][$key])) {
            return $this->pending_translations[$default_locale][$key];
        }

        // Then check saved translation relation
        $translation = $this->getRelationValue('translation');

        if ($translation && isset($translation->{$key})) {
            return $translation->{$key};
        }

        // Fallback to default translation if enabled
        if ($fallback_enabled) {
            $default_translation = $this->getDefaultTranslation();

            if ($default_translation && isset($default_translation->{$key})) {
                return $default_translation->{$key};
            }
        }

        return null;
    }

    /**
     * Set value for a translatable field.
     *
     * Stores the value in pending_translations to be persisted on save().
     *
     * @param  string  $key  The field name
     * @param  mixed  $value  The value to set
     */
    protected function setTranslatableFieldValue(string $key, $value): void
    {
        $locale = $this->getCurrentLocale();

        // Store in pending translations to be saved on save()
        if (! isset($this->pending_translations[$locale])) {
            $this->pending_translations[$locale] = [];
        }

        $this->pending_translations[$locale][$key] = $value;
    }

    /**
     * Get the current locale for setter operations.
     * This is a helper method used internally by the trait.
     */
    private function getCurrentLocale(): string
    {
        return $this->current_setter_locale ?? LocaleContext::get();
    }
}
