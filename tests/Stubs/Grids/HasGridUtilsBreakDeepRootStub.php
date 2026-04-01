<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsBreakDeepRootStub extends Model
{
    use HasGridUtils;

    protected $table = 'break_root';

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsBreakDeepChildStub::class, 'root_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
