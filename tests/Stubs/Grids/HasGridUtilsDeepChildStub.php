<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsDeepChildStub extends Model
{
    use HasGridUtils;

    protected $table = 'child';

    public function parentModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HasGridUtilsDeepParentStub::class, 'parent_id', 'id');
    }

    public function leaves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsDeepLeafStub::class, 'child_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
