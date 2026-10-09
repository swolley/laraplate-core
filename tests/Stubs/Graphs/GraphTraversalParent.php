<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GraphTraversalParent extends Model
{
    protected $table = 'graph_traversal_parents';

    protected $guarded = [];

    /**
     * @return HasMany<GraphTraversalChild, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(GraphTraversalChild::class, 'parent_id');
    }
}
