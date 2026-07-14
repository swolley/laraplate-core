<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;

final class GraphTraversalChild extends Model
{
    protected $table = 'graph_traversal_children';

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(GraphTraversalParent::class, 'parent_id');
    }
}
