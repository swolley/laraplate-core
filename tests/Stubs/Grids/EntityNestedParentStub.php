<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntityNestedParentStub extends Model
{
    use HasGridUtils;

    protected $table = 'nested_parent';

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EntityNestedChildStub::class, 'parent_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
