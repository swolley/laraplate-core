<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsInverseTypedTargetStub extends Model
{
    use HasGridUtils;

    protected $table = 'inverse_typed_targets';

    public function source(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HasGridUtilsInverseBaseSourceStub::class, 'source_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
