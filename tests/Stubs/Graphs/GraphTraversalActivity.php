<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;

final class GraphTraversalActivity extends Model
{
    protected $table = 'graph_traversal_activities';

    protected $guarded = [];

    public function subject()
    {
        return $this->morphTo();
    }
}
