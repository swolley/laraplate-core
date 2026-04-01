<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsDatesStub extends Model
{
    use HasGridUtils;

    protected $table = 'dates_table';

    protected $fillable = ['name'];

    protected $dates = ['legacy_date'];

    protected function casts(): array
    {
        return [];
    }
}
