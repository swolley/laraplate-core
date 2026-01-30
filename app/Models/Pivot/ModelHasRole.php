<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Modules\Core\Observers\ModelHasRoleObserver;

#[ObservedBy([ModelHasRoleObserver::class])]
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
