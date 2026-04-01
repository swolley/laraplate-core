<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntityNestedChildStub extends Model
{
    use HasGridUtils;

    protected $table = 'nested_child';

    public function leaves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EntityNestedLeafStub::class, 'child_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
