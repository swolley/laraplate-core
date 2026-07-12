<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * Materialized graph edge used by optional graph performance layers.
 *
 * @property int|null $id
 * @property string $edge_key
 * @property string $source_node_id
 * @property string $target_node_id
 * @property string $relation
 * @property string $relation_path
 * @property string|null $type
 * @property bool $directed
 * @property array<string, mixed>|null $metadata
 * @mixin \Eloquent
 * @mixin IdeHelperGraphEdge
 */
final class GraphEdge extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'edge_key',
        'source_module',
        'source_entity',
        'source_key',
        'source_node_id',
        'target_module',
        'target_entity',
        'target_key',
        'target_node_id',
        'relation',
        'relation_path',
        'type',
        'directed',
        'metadata',
        'stale_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::GraphEdges->value;

    /**
     * @param  Builder<GraphEdge>  $query
     * @return Builder<GraphEdge>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function notStale(Builder $query): Builder
    {
        return $query->whereNull('stale_at');
    }

    protected function casts(): array
    {
        return [
            'directed' => 'boolean',
            'metadata' => 'array',
            'stale_at' => 'datetime',
        ];
    }
}
