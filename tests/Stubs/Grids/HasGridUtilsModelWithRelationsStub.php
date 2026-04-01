<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsModelWithRelationsStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name'];

    public function roles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HasGridUtilsChildModelStub::class, 'user_id', 'id');
    }

    protected function casts(): array
    {
        return [];
    }
}
