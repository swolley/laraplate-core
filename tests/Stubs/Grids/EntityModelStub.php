<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntityModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    public function getRules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'email'],
        ];
    }

    protected function casts(): array
    {
        return [];
    }
}
