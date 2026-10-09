<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class GraphTraversalActivity extends Model
{
    protected $table = 'graph_traversal_activities';

    protected $guarded = [];

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
