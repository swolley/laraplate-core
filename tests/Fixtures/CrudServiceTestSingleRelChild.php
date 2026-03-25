<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class CrudServiceTestSingleRelChild extends Model
{
    protected $table = 'crud_single_rel_child';

    protected $guarded = [];

    public function childLabel(): string
    {
        return 'single';
    }
}