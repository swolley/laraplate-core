<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeders;

use Illuminate\Database\Eloquent\Model;

final class SeedersRelationTagStubModel extends Model
{
    protected $table = 'seeders_relation_tags';

    protected $fillable = ['name'];
}
