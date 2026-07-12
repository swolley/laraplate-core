<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphRelationInspector;

final class GraphInspectorParent extends Model
{
    protected $table = 'graph_inspector_parents';

    public function children(): HasMany
    {
        return $this->hasMany(GraphInspectorChild::class, 'parent_id');
    }
}

final class GraphInspectorChild extends Model
{
    protected $table = 'graph_inspector_children';
}

it('inspects normal eloquent relations', function (): void {
    $relation = (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'children');

    expect($relation->name)->toBe('children');
    expect($relation->relatedClass)->toBe(GraphInspectorChild::class);
    expect($relation->isMultiple)->toBeTrue();
});

it('rejects missing relations with validation errors', function (): void {
    expect(fn () => (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'missing'))
        ->toThrow(ValidationException::class);
});
