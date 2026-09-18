<?php

declare(strict_types=1);

namespace Modules\Core\Console\Concerns;

use function Laravel\Prompts\select;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasCommandModelResolution
{
    protected function getModelClass(string $optionName, ?string $namespace = null, bool $required = true, ?callable $filter = null): string|false
    {
        $model = $this->getModelFromCommand($optionName);

        if (! $model && ! $required) {
            return false;
        }

        if (! $model && $required) {
            $all_models = models(false, filter: $filter);
            $model = $this->askForUserInput($optionName, $all_models);
        }

        if (! in_array($namespace, [null, '', '0'], true)) {
            $model = sprintf('%s\%s', $namespace, $model);
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
        $value = match (true) {
            $this->hasArgument($optionName) => $this->argument($optionName),
            $this->hasOption($optionName) => $this->option($optionName),
            default => null,
        };

        // argument() and option() return mixed: an array here means the option was
        // declared repeatable, which this resolver does not handle.
        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string>  $all_models
     */
    private function askForUserInput(string $optionName, array $all_models): string
    {
        // select() returns int|string: with a list of class names the key is an int,
        // and the caller wants the name.
        $choice = select(
            label: sprintf('What is the %s?', $optionName),
            options: $all_models,
            required: true,
        );

        return is_string($choice) ? $choice : ($all_models[$choice] ?? '');
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
