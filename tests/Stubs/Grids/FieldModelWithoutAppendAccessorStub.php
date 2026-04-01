<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class FieldModelWithoutAppendAccessorStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name'];

    protected $appends = ['missing_accessor'];

    public function getRules(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [];
    }
}
