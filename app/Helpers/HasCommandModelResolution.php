<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function Laravel\Prompts\select;

use Illuminate\Support\Str;

trait HasCommandModelResolution
{
    protected function getModelClass(string $optionName, ?string $namespace = null, bool $required = true): string|false
    {
        if ($this->hasArgument($optionName)) {
            $model = $this->argument($optionName);
        } elseif ($this->hasOption($optionName)) {
            $model = $this->option($optionName);
        } else {
            $model = null;
        }

        if (! $model && $required) {
            $all_models = models(false);
            $model = select(
                label: "What is the {$optionName}?",
                options: $all_models,
                required: true,
            );
        } elseif (! $model) {
            return false;
        }

        if ($namespace) {
            $model = "{$namespace}\\{$model}";
        }

        if (! class_exists($model)) {
            $model = array_filter($all_models ?? models(false), fn (string $m) => (Str::contains($model, '\\') && $model === $m) || Str::endsWith($m, $model));

            if (count($model) === 0) {
                $this->error('Model not found');

                return false;
            }

            if (count($model) > 1) {
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
}
