<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with a plain column named like a black-listed relation (`children`), to group on.
 */
final class GroupByColumnStub extends Model
{
    public $timestamps = false;

    protected $table = 'relauth_group_stub';

    protected $guarded = [];
}
