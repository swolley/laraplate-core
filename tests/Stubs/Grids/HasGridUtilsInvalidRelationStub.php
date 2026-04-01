<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsInvalidRelationStub extends Model
{
    use HasGridUtils;

    protected $table = 'invalid_rel';

    public function notARelation()
    {
        return 'nope';
    }

    protected function casts(): array
    {
        return [];
    }
}
