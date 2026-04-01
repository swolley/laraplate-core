<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsDeletedColumnStub extends Model
{
    use HasGridUtils;

    protected $table = 'deleted_col';

    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    protected function casts(): array
    {
        return [];
    }
}
