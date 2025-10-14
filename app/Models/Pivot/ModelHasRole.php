<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * @mixin IdeHelperModelHasRole
 */
final class ModelHasRole extends MorphPivot
{
    use HasFactory;
    // protected $attributes = [
    // 	'team_id' => 1,
    // ];
}
