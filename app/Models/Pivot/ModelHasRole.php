<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Observers\ModelHasRoleObserver;
use Override;

#[ObservedBy([ModelHasRoleObserver::class])]
/**
 * @mixin \Eloquent
 * @mixin IdeHelperModelHasRole
 */
final class ModelHasRole extends MorphPivot
{
    use HasFactory;

    #[Override]
    protected $table = CoreTables::ModelHasRoles->value;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (config('permission.teams') && ! isset($this->attributes['team_id'])) {
            $this->attributes['team_id'] = 1;
        }
    }
}
