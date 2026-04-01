<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsChildModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'roles';

    protected $fillable = ['name', 'user_id'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HasGridUtilsModelWithRelationsStub::class, 'user_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
