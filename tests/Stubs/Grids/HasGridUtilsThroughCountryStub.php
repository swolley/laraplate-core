<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsThroughCountryStub extends Model
{
    use HasGridUtils;

    protected $table = 'countries';

    public function posts(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            HasGridUtilsThroughPostStub::class,
            HasGridUtilsThroughUserStub::class,
            'country_id',
            'user_id',
            'id',
            'id',
        );
    }

    protected function casts(): array
    {
        return [];
    }
}
