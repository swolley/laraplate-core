<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsModelWithPlainRelationStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    public function plain(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HasGridUtilsPlainRelatedModelStub::class, 'id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
