<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Field;

final class FieldObserver
{
    /**
     * Attributes that define where a field's data physically lives and how it is
     * stored: once the field is linked to a preset they are frozen, because the
     * presettable snapshots (and the content written under them) rely on them.
     * Changing them would orphan existing data. Other attributes (options,
     * is_active, and pivot-level is_required) remain freely editable.
     *
     * @var list<string>
     */
    private const array LOCKED_WHEN_LINKED = ['type', 'is_translatable'];

    /**
     * Handle the Field "updating" event.
     */
    public function updating(Field $model): void
    {
        $this->guardStructuralImmutability($model);

        if (property_exists($model, 'pivot') && $model->pivot && $model->pivot->isDirty()) {
            $model->pivot->save();
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardStructuralImmutability(Field $model): void
    {
        $locked_dirty = array_values(array_intersect(self::LOCKED_WHEN_LINKED, array_keys($model->getDirty())));

        if ($locked_dirty === [] || ! $this->isLinkedToPreset($model)) {
            return;
        }

        $messages = [];

        foreach ($locked_dirty as $attribute) {
            $messages[$attribute] = "The field '{$attribute}' cannot be changed once the field is linked to a preset; create a new field instead.";
        }

        throw ValidationException::withMessages($messages);
    }

    private function isLinkedToPreset(Field $model): bool
    {
        return $model->getConnection()->table(CoreTables::Fieldables->value)
            ->where('field_id', $model->getKey())
            ->exists();
    }
}
