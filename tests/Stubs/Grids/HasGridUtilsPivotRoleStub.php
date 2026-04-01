<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsPivotRoleStub extends Model
{
    use HasGridUtils;

    protected $table = 'pivot_roles';

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(HasGridUtilsPivotPermissionStub::class, 'role_permission', 'role_id', 'permission_id');
    }

    protected function casts(): array
    {
        return [];
    }
}
