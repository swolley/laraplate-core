<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\PresetVersioningService;
use ReflectionClass;

final readonly class FieldableObserver
{
    public function __construct(private PresetVersioningService $versioning) {}

    public function saved(Fieldable $fieldable): void
    {
        $this->createVersionForPreset($fieldable);
    }

    public function deleted(Fieldable $fieldable): void
    {
        $this->createVersionForPreset($fieldable);
    }

    private function createVersionForPreset(Fieldable $fieldable): void
    {
        $preset = $this->resolvePreset($fieldable);

        if (! $preset instanceof Preset) {
            return;
        }

        $this->versioning->createVersion($preset);
    }

    private function resolvePreset(Fieldable $fieldable): ?Preset
    {
        if ($fieldable->preset_id === null) {
            return null;
        }

        foreach (models(filter: $this->isConcretePresetClass(...)) as $preset_class) {
            $preset = $preset_class::query()->find($fieldable->preset_id);

            if ($preset instanceof Preset) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $model
     */
    private function isConcretePresetClass(string $model): bool
    {
        if (! is_subclass_of($model, Preset::class)) {
            return false;
        }

        return ! (new ReflectionClass($model))->isAbstract();
    }
}
