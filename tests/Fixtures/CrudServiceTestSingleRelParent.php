<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CrudServiceTestSingleRelParent extends Model
{
    protected $table = 'crud_single_rel_parent';

    protected $guarded = [];

    public function childRecord(): HasOne
    {
        return $this->hasOne(CrudServiceTestSingleRelChild::class, 'parent_id', 'id');
    }
}