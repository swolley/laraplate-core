<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;

/**
 * A related record with a plain `name` column and nothing dynamic about it.
 */
class PlainTargetStubModel extends Model
{
    public $timestamps = false;

    protected $table = 'plain_target_stub';

    protected $fillable = ['name'];
}
