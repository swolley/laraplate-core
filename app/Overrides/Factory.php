<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Factories\Factory as BaseFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Helpers\HasDynamicContentFactory;
use Modules\Cms\Helpers\HasDynamicContents;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Helpers\HasTranslations;
use Modules\Core\Helpers\HasTranslationsFactory;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Override;
use Throwable;

abstract class Factory extends BaseFactory
{
    use HasDynamicContentFactory, HasTranslationsFactory, HasUniqueFactoryValues;

    abstract protected function definitionsArray(): array;

    #[Override]
    public function configure(): static
    {
        return parent::configure()
            ->afterMaking(function (Model $model): void {
                $this->beforeFactoryMaking($model);

                if ($this->usesDynamicContents()) {
                    $this->fillDynamicContents($model);
                }

                if ($this->usesApprovals()) {
                    $model->setForcedApprovalUpdate(fake()->boolean(85));
                }

                $this->afterFactoryMaking($model);
            })
            ->afterCreating(function (Model $model): void {
                $this->beforeFactoryCreating($model);

                if (! $this->usesTranslations()) {
                    $this->afterFactoryCreating($model);

                    return;
                }

                $default_locale = config('app.locale');

                if (! $model->translations()->where('locale', $default_locale)->exists()) {
                    $default_translation_data = $this->translatedFieldsArray($model);

                    if ($default_translation_data === []) {
                        $this->afterFactoryCreating($model);

                        return;
                    }

                    $model->setTranslation($default_locale, $default_translation_data);
                }

                try {
                    $this->createTranslations($model, fn (string $locale): array => $this->translationOverrides($model, $locale));
                } catch (Throwable) {
                    // Factories should not fail hard if translations duplication is optional for a given model.
                    // Concrete factories can still create translations explicitly when required for a test.
                }

                $this->afterFactoryCreating($model);
            });
    }

    #[Override]
    public function definition(): array
    {
        $dynamic_definition = $this->usesDynamicContents() ? $this->dynamicContentDefinition() : [];
        // $translations_definition = class_uses_trait(HasTranslations::class, $model_name) ? $this->translationsDefinition() : [];

        return $this->definitionsArray() + $dynamic_definition;
    }

    /**
     * Hook executed inside the base afterMaking callback, before core behaviors.
     */
    protected function beforeFactoryMaking(Model $model): void {}

    /**
     * Hook executed inside the base afterMaking callback, after core behaviors.
     */
    protected function afterFactoryMaking(Model $model): void {}

    /**
     * Hook executed inside the base afterCreating callback, before core behaviors.
     */
    protected function beforeFactoryCreating(Model $model): void {}

    /**
     * Hook executed inside the base afterCreating callback, after core behaviors.
     */
    protected function afterFactoryCreating(Model $model): void {}

    /**
     * Provide default-locale translation data when the default translation does not exist yet.
     *
     * Return an empty array to skip creating the default translation at base level.
     *
     * @return array<string, mixed>
     */
    protected function translatedFieldsArray(Model $model): array
    {
        return [];
    }

    /**
     * Override per factory to customize locale-specific translation values.
     *
     * @return array<string, mixed>
     */
    protected function translationOverrides(Model $model, string $locale): array
    {
        return [];
    }

    private function usesDynamicContents(): bool
    {
        return $this->usesTraits(HasDynamicContents::class);
    }

    private function usesTranslations(): bool
    {
        return $this->usesTraits(HasTranslations::class);
    }

    private function usesApprovals(): bool
    {
        return $this->usesTraits(HasApprovals::class);
    }

    private function usesTraits(string $trait): bool
    {
        return class_uses_trait($this->model, $trait);
    }
}
