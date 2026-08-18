<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class CrudSyncRelChild extends Model
{
    public $timestamps = false;

    protected $table = 'crud_sync_rel_child';

    protected $guarded = [];
}
