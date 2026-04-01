<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeders;

use Illuminate\Database\Eloquent\Model;

final class SeedersRelationParentStubModel extends Model
{
    protected $table = 'seeders_relation_parents';

    protected $fillable = ['name'];
}
