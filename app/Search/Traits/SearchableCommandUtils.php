<?php

namespace Modules\Core\Search\Traits;

use Illuminate\Support\Str;

trait SearchableCommandUtils
{
    private function getModelClass(): string|false
    {
        $model = (string) $this->argument('model');
        if (!class_exists($model)) {
            $model = array_filter(models(false), fn(string $m) => (Str::contains($model, '\\') && $model === $m) || Str::endsWith($m, $model));
            if (count($model) === 0) {
                $this->error('Model not found');
                return false;
            }
            if (count($model) > 1) {
                $this->error('Multiple models found: ' . implode(', ', $model));
                return false;
            }
            /** @var class-string $modelq */
            $model = head($model);
            if (!class_exists($model)) {
                $this->error('Model not found');
                return false;
            }
        }
        if (!class_uses_trait($model, Searchable::class)) {
            $this->error('Model does not use Searchable trait');
            return false;
        }

        $this->input->setArgument('model', $model);
        return $model;
    }
}
