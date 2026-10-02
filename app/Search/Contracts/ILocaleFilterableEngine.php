<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * An engine that can restrict a search to the documents available in a language, as part of the engine
 * query. The restriction is a `locales` where clause on the builder; an engine without this capability
 * would read it as an attribute the models do not have and return nothing, so the search layer adds it
 * only where this is implemented and {@see self::filtersByLocale()} says the index has the field.
 */
interface ILocaleFilterableEngine
{
    /**
     * Whether the model's index records the languages each document is available in.
     */
    public function filtersByLocale(Model $model): bool;
}
