<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;

final class GraphTraversalParent extends Model
{
    protected $table = 'graph_traversal_parents';

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(GraphTraversalChild::class, 'parent_id');
    }
}
