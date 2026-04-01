<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;
use RuntimeException;

final class HasGridUtilsModelWithBrokenRelationStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    public function broken(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        throw new RuntimeException('broken relation');
    }

    protected function casts(): array
    {
        return [];
    }
}
