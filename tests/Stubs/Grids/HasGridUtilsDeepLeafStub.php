<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsDeepLeafStub extends Model
{
    use HasGridUtils;

    protected $table = 'leaf';

    public function child(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HasGridUtilsDeepChildStub::class, 'child_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
