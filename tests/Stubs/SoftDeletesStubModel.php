<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\SoftDeletes\SoftDeletes;

class SoftDeletesStubModel extends Model
{
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'soft_deletes_stub';

    protected $fillable = ['name'];
}
