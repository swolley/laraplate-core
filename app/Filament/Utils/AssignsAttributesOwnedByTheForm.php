<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IOptimisticLockableModel;

/**
 * Fills a record from the state of a Filament form, including what the model keeps out of `$fillable` on
 * purpose and the form still edits: the translated fields, which the setter routes to the translation
 * table, and the sort column. Mass assignment discards them (and throws outside production), so they are
 * assigned one by one; anything else the model guards stays guarded.
 *
 * @phpstan-require-extends \Filament\Resources\Pages\CreateRecord|\Filament\Resources\Pages\EditRecord
 */
trait AssignsAttributesOwnedByTheForm
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function fillFromFormState(Model $record, array $data): void
    {
        // The form carries the lock version as a hidden field; the model owns it and starts a new record at 1.
        if ($record instanceof IOptimisticLockableModel) {
            unset($data[$record::lockVersionColumn()]);
        }

        $sort_column = method_exists($record, 'determineOrderColumnName') ? $record->determineOrderColumnName() : null;

        $owned = array_filter(
            $data,
            static fn (string $attribute): bool => $attribute === $sort_column
                || (method_exists($record, 'isTranslatableField') && $record->isTranslatableField($attribute)),
            ARRAY_FILTER_USE_KEY,
        );

        $record->fill(array_diff_key($data, $owned));

        foreach ($owned as $attribute => $value) {
            $record->setAttribute($attribute, $value);
        }
    }
}
