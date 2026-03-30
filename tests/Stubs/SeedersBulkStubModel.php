<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class SeedersBulkStubModel extends Model
{
    public $timestamps = true;

    protected $table = 'seeders_bulk_stub';

    protected $fillable = ['name'];
}
