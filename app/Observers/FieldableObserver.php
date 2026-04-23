<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\PresetVersioningService;

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
        $preset = $fieldable->preset; // ?? Preset::query()->find($fieldable->preset_id);

        if (! $preset) {
            return;
        }

        $this->versioning->createVersion($preset);
    }
}
