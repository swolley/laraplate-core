<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Graphs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GraphInspectorParent extends Model
{
    protected $table = 'graph_inspector_parents';

    public function children(): HasMany
    {
        return $this->hasMany(GraphInspectorChild::class, 'parent_id');
    }
}
