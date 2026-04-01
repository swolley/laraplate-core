<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsBreakDeepChildStub extends Model
{
    use HasGridUtils;

    protected $table = 'break_child';

    public function leaves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsBreakDeepLeafStub::class, 'child_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
