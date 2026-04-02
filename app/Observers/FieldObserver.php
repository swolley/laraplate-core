<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\Field;

final class FieldObserver
{
    /**
     * Handle the Field "updating" event.
     */
    public function updating(Field $model): void
    {
        if (property_exists($model, 'pivot') && $model->pivot && $model->pivot->isDirty()) {
            $model->pivot->save();
        }
    }
}
