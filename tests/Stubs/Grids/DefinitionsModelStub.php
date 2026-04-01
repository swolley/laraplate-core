<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class DefinitionsModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name'];

    public function getRules(): array
    {
        return [
            'name' => ['required'],
        ];
    }

    protected function casts(): array
    {
        return [];
    }
}
