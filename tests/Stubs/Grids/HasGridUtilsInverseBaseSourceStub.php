<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

class HasGridUtilsInverseBaseSourceStub extends Model
{
    use HasGridUtils;

    protected $table = 'inverse_base_sources';

    public function targets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsInverseTypedTargetStub::class, 'source_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
