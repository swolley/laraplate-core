<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsInverseSourceStub extends Model
{
    use HasGridUtils;

    protected $table = 'inverse_source';

    public function targets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsInverseTargetStub::class, 'source_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
