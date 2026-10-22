<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use Modules\Core\Contracts\INeverVersionedModel;
use Modules\Core\Services\Translation\Definitions\ITranslated;

/**
 * The models that stay out of the history whatever their per-table setting says: comments and ratings, which
 * are not part of the document (D8), and the translation models, whose state belongs to the owner (D10). Read
 * by the models themselves, by the seeder, which creates no versioning setting for them, and by the settings
 * panel, which hides and refuses a row left from an older seed.
 */
final class NeverVersionedModels
{
    /**
     * @param  class-string  $modelClass
     */
    public static function includes(string $modelClass): bool
    {
        return is_a($modelClass, INeverVersionedModel::class, true) || is_a($modelClass, ITranslated::class, true);
    }
}
