<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GraphTraversalChild extends Model
{
    protected $table = 'graph_traversal_children';

    protected $guarded = [];

    /**
     * @return BelongsTo<GraphTraversalParent, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GraphTraversalParent::class, 'parent_id');
    }
}
