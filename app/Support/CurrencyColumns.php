<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The column that holds the currency of an amount (aggregate spec 4.6).
 *
 * An amount named `<x>_<suffix>` is paired with a column named `currency_<suffix>` (`amount_doc` with
 * `currency_doc`, `amount_local` with `currency_local`), and any other amount, or one whose paired column the table
 * does not have, with the column named `currency`. A field with no underscore is paired by its whole name. A
 * table with neither column keeps no currency: its amounts take the currency the request formats them with.
 *
 * The answer is a column of the table of the model, so it is safe to use in SQL once qualified and wrapped.
 */
final class CurrencyColumns
{
    private const string DEFAULT_COLUMN = 'currency';

    /**
     * The currency column of the amount in the field, or null when the table has none for it.
     */
    public static function for(Model $model, string $field): ?string
    {
        $suffix = Str::afterLast($field, '_');
        $candidates = $suffix === '' ? [self::DEFAULT_COLUMN] : [self::DEFAULT_COLUMN . '_' . $suffix, self::DEFAULT_COLUMN];

        foreach ($candidates as $column) {
            if (ColumnKinds::exists($model, $column)) {
                return $column;
            }
        }

        return null;
    }
}
