<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    protected $hidden = ['password'];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return 'test';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
