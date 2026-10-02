<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EntityType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Preset as CorePreset;
use Override;

/**
 * Test-only App preset: only sees presets whose entity has an App {@see EntityType}.
 */
final class Preset extends CorePreset
{
    #[Override]
    protected static function getRelatedModelClass(): string
    {
        return Page::class;
    }

    #[Override]
    protected function newBaseQueryBuilder(): Builder
    {
        return parent::newBaseQueryBuilder()->whereExists(static function (Builder $query): void {
            $entities_table = CoreTables::Entities->value;
            $query->select(DB::raw('1'))
                ->from($entities_table)
                ->whereColumn("{$entities_table}.id", CoreTables::Presets->value . '.entity_id')
                ->whereIn("{$entities_table}.type", EntityType::values());
        });
    }
}
