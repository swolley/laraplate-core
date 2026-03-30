<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasClosureTable;

class ClosureTreeStubModel extends Model
{
    use HasClosureTable;

    protected $table = 'closure_tree_nodes';

    protected $fillable = ['parent_id'];

    protected $casts = [
        'parent_id' => 'integer',
    ];
}
