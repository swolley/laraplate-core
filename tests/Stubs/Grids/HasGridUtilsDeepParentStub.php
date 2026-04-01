<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsDeepParentStub extends Model
{
    use HasGridUtils;

    protected $table = 'parent';

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsDeepChildStub::class, 'parent_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
