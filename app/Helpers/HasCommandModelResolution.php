<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function Laravel\Prompts\select;

use Illuminate\Support\Str;

trait HasCommandModelResolution
{
    protected function getModelClass(string $optionName, ?string $namespace = null, bool $required = true): string|false
    {
        $model = $this->getModelFromCommand($optionName);

        if (! $model && ! $required) {
            return false;
        }

        if (! $model && $required) {
            $all_models = models(false);
            $model = $this->askForUserInput($optionName, $all_models);
        }

        if ($namespace !== null && $namespace !== '' && $namespace !== '0') {
            $model = "{$namespace}\\{$model}";
        }

        if (! class_exists($model)) {
            $model = $this->evinceFromExistingModels($model, $all_models ?? models(false));
            $count = count($model);

            if ($count === 0) {
                $this->error('Model not found');

                return false;
            }

            if ($count > 1) {
                $this->error('Multiple models found: ' . implode(', ', $model));

                return false;
            }

            /** @var class-string<Model> $model */
            $model = head($model);

            if (! class_exists($model)) {
                $this->error('Model not found');

                return false;
            }
        }

        return $model;
    }

    private function getModelFromCommand(string $optionName): ?string
    {
        return match (true) {
            $this->hasArgument($optionName) => $this->argument($optionName),
            $this->hasOption($optionName) => $this->option($optionName),
            default => null,
        };
    }

    /**
     * @param  array<string>  $all_models
     */
    private function askForUserInput(string $optionName, array $all_models): ?string
    {
        return select(
            label: "What is the {$optionName}?",
            options: $all_models,
            required: true,
        );
    }

    /**
     * @param  array<class-string<Model>>  $all_models
     * @return array<class-string<Model>>
     */
    private function evinceFromExistingModels(string $model, array $all_models): array
    {
        return array_filter($all_models, fn (string $m): bool => (Str::contains($model, '\\') && $model === $m) || Str::endsWith($m, $model));
    }
}
